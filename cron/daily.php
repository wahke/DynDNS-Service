<?php
require_once __DIR__ . '/../bootstrap.php';

/**
 * HTML-Mails mit Branding (.env):
 * - BRAND_NAME, BRAND_LOGO_URL, BRAND_IMPRINT_URL, BRAND_PRIVACY_URL, BRAND_TERMS_URL
 * - EMAIL_PRIMARY_COLOR (z.B. #4e7be6)
 * - SUPPORT_EMAIL
 *
 * Testmodus (CLI):
 *  php cron/daily.php --user=123 --phase=T-14 --dry-run=1
 */

$dryRunEnv = !empty(($GLOBALS['env']['DRY_RUN_CLEANUP'] ?? '')) && ($GLOBALS['env']['DRY_RUN_CLEANUP'] ?? '') !== '0';

$opts = ['user'=>null, 'phase'=>null, 'dry-run'=>null];
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (preg_match('~^--user=(\\d+)~', $arg, $m)) { $opts['user'] = (int)$m[1]; continue; }
    if (preg_match('~^--phase=(T-14|T0|T\\+1)$~', $arg, $m)) { $opts['phase'] = $m[1]; continue; }
    if ($arg === '--dry-run=1' || $arg === '--dry-run') { $opts['dry-run'] = 1; continue; }
}
$dryRunCli = !empty($opts['dry-run']);
$dryRun = $dryRunEnv || $dryRunCli;

$hz = new HetznerClient($env);

function ensureAnnualToken(PDO $pdo, int $uid): string {
    $stmt = $pdo->prepare('SELECT annual_confirm_token FROM users WHERE id=?');
    $stmt->execute([$uid]);
    $tok = $stmt->fetchColumn();
    if (!$tok) {
        $tok = Util::randToken(64);
        $pdo->prepare('UPDATE users SET annual_confirm_token=? WHERE id=?')->execute([$tok, $uid]);
    }
    return $tok;
}

function reminderMail(PDO $pdo, array $env, int $uid, int $daysLeft, string $due, string $to, string $username, string $link, bool $dryRun) {
    $subject = $daysLeft <= 0 ? "DynDNS – Heute fällig: bitte bestätigen" : "DynDNS – Erinnerung: Bestätigung bis {$due} nötig";
    $text  = "Hallo {$username},\n\n";
    $text .= ($daysLeft <= 0)
             ? "heute läuft deine jährliche Bestätigung ab.\n"
             : "in {$daysLeft} Tagen (am {$due}) läuft deine jährliche Bestätigung ab.\n";
    $text .= "Bitte bestätige hier, um deine Subdomain(s) zu behalten:\n{$link}\n\n";
    $text .= "Ohne Bestätigung wird dein Konto am Fälligkeitstag deaktiviert und am Folgetag samt DNS-Einträgen gelöscht.\n\n";
    $text .= "Viele Grüße\n" . Util::brandName($env);

    $htmlContent = '<p>Hallo '.Util::html($username).',</p>'.
        (($daysLeft<=0) ? '<p>heute läuft deine jährliche Bestätigung ab.</p>' : '<p>in <strong>'.$daysLeft.' Tagen</strong> (am <strong>'.Util::html($due).'</strong>) läuft deine jährliche Bestätigung ab.</p>').
        '<p>Bitte bestätige hier, um deine Subdomain(s) zu behalten:</p>'.
        '<p><a href="'.Util::html($link).'">'.Util::html($link).'</a></p>'.
        '<p style="color:#77809a">Ohne Bestätigung wird dein Konto am Fälligkeitstag deaktiviert und am Folgetag samt DNS-Einträgen gelöscht.</p>';
    $html = Util::emailTemplate($env, 'Jährliche Bestätigung', $htmlContent, $link, 'Jetzt bestätigen');

    if ($dryRun) {
        echo "[dry-run] would send REMINDER to {$to}\nSubject: {$subject}\n";
    } else {
        Util::sendMailHtml($to, $subject, $text, $html);
    }
}

function deletionMail(PDO $pdo, array $env, string $to, string $username, bool $dryRun) {
    $subject = "DynDNS – Account gelöscht";
    $text = "Hallo {$username},\n\nleider hast du die jährliche Bestätigung nicht durchgeführt. Dein Account und die zugehörigen DNS-Einträge wurden gelöscht.\n\nFalls das ein Versehen war, kannst du dich jederzeit erneut registrieren.\n\nViele Grüße\n".Util::brandName($env);
    $htmlContent = '<p>Hallo '.Util::html($username).',</p><p>leider hast du die jährliche Bestätigung nicht durchgeführt. Dein Account und die zugehörigen DNS-Einträge wurden gelöscht.</p><p>Falls das ein Versehen war, kannst du dich jederzeit erneut registrieren.</p>';
    $html = Util::emailTemplate($env, 'Account gelöscht', $htmlContent, null, null);

    if ($dryRun) {
        echo "[dry-run] would send DELETION to {$to}\nSubject: {$subject}\n";
    } else {
        Util::sendMailHtml($to, $subject, $text, $html);
    }
}

function cleanupUser(PDO $pdo, array $env, HetznerClient $hz, int $uid, bool $dryRun): void {
    $stmt = $pdo->prepare('SELECT hetzner_record_id_a, hetzner_record_id_aaaa FROM records WHERE user_id=?');
    $stmt->execute([$uid]);
    $recs = $stmt->fetchAll();
    foreach ($recs as $r) {
        foreach (['hetzner_record_id_a','hetzner_record_id_aaaa'] as $key) {
            $rid = $r[$key] ?? null;
            if ($rid) {
                if ($dryRun) echo "[dry-run] Hetzner DELETE skip: $rid\n";
                else { try { $hz->deleteRecord($rid); echo "Hetzner DELETE ok: $rid\n"; } catch (Throwable $e) { error_log("Hetzner delete failed for $rid: ".$e->getMessage()); } }
            }
        }
    }
    if ($dryRun) echo "[dry-run] DB DELETE users.id=$uid (CASCADE)\n";
    else { $pdo->prepare('DELETE FROM users WHERE id=? LIMIT 1')->execute([$uid]); echo "DB DELETE users.id=$uid\n"; }
}

// ---- TESTMODUS ----
if ($opts['user'] && $opts['phase']) {
    $uid = (int)$opts['user'];
    $stmt = $pdo->prepare('SELECT id, email, username, annual_confirm_due FROM users WHERE id=?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if (!$user) { echo "User not found\n"; exit(1); }

    if ($opts['phase'] === 'T-14') {
        $tok = ensureAnnualToken($pdo, $uid);
        $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($tok);
        reminderMail($pdo, $env, $uid, 14, $user['annual_confirm_due'], $user['email'], $user['username'], $link, $dryRun);
    } elseif ($opts['phase'] === 'T0') {
        $tok = ensureAnnualToken($pdo, $uid);
        $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($tok);
        reminderMail($pdo, $env, $uid, 0, $user['annual_confirm_due'], $user['email'], $user['username'], $link, $dryRun);
        if ($dryRun) echo "[dry-run] would set is_active=0\n";
        else $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$uid]);
    } elseif ($opts['phase'] === 'T+1') {
        deletionMail($pdo, $env, $user['email'], $user['username'], $dryRun);
        cleanupUser($pdo, $env, $hz, $uid, $dryRun);
    }
    exit(0);
}

// ---- NORMALBETRIEB ----

// T-14
$q14 = $pdo->query("SELECT id, email, username, annual_confirm_due FROM users WHERE annual_confirm_due = DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
foreach ($q14 as $row) {
    $tok = ensureAnnualToken($pdo, (int)$row['id']);
    $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($tok);
    reminderMail($pdo, $env, (int)$row['id'], 14, $row['annual_confirm_due'], $row['email'], $row['username'], $link, $dryRun);
}
// T0
$q0 = $pdo->query("SELECT id, email, username, annual_confirm_due FROM users WHERE annual_confirm_due = CURDATE()");
foreach ($q0 as $row) {
    $tok = ensureAnnualToken($pdo, (int)$row['id']);
    $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($tok);
    reminderMail($pdo, $env, (int)$row['id'], 0, $row['annual_confirm_due'], $row['email'], $row['username'], $link, $dryRun);
    $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([(int)$row['id']]);
}
// T+1
$qx = $pdo->query("SELECT id, email, username FROM users WHERE annual_confirm_due < CURDATE()");
foreach ($qx as $u) {
    deletionMail($pdo, $env, $u['email'], $u['username'], $dryRun);
    cleanupUser($pdo, $env, $hz, (int)$u['id'], $dryRun);
}

echo "OK " . date('Y-m-d H:i:s') . ($dryRun ? " (dry-run)" : "") . PHP_EOL;
