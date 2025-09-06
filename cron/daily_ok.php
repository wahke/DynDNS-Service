<?php
require_once __DIR__ . '/../bootstrap.php';

/**
 * Zeitliche Annahmen (täglich via Cron ausführen):
 * - T-14: 14 Tage vor Fälligkeit → Reminder mit Bestätigungslink
 * - T0  : am Fälligkeitstag → Reminder + Account deaktivieren (is_active=0)
 * - T+1 : am Tag nach Fälligkeit → DNS-Records bei Hetzner löschen, Account-Löschmail senden, Benutzer in DB löschen (CASCADE)
 */

$today = (new DateTimeImmutable('today'))->format('Y-m-d');

// Stelle sicher, dass alle in 14 Tagen fälligen Nutzer ein Token haben und sende T-14 Reminder
$q14 = $pdo->prepare("SELECT id, email, username, annual_confirm_due, annual_confirm_token FROM users WHERE annual_confirm_due = DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
$q14->execute();
$hz = new HetznerClient($env);

foreach ($q14 as $row) {
    $token = $row['annual_confirm_token'];
    if (!$token) {
        $token = Util::randToken(64);
        $pdo->prepare('UPDATE users SET annual_confirm_token=? WHERE id=?')->execute([$token, (int)$row['id']]);
    }
    $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($token);
    $msg = "Hallo {$row['username']},\n\nin 14 Tagen (am {$row['annual_confirm_due']}) läuft deine jährliche Bestätigung ab. Bitte bestätige hier, um deine Subdomain(s) zu behalten:\n$link\n\nDanke.\n";
    Util::sendMailStub($row['email'], 'DynDNS – Erinnerung (14 Tage vorher)', $msg);
}

// Am Fälligkeitstag: Reminder + deaktivieren
$q0 = $pdo->prepare("SELECT id, email, username, annual_confirm_due, annual_confirm_token FROM users WHERE annual_confirm_due = CURDATE()");
$q0->execute();
foreach ($q0 as $row) {
    $token = $row['annual_confirm_token'];
    if (!$token) {
        $token = Util::randToken(64);
        $pdo->prepare('UPDATE users SET annual_confirm_token=? WHERE id=?')->execute([$token, (int)$row['id']]);
    }
    $link = Util::baseUrl($env) . '/confirm.php?t=' . urlencode($token);
    $msg = "Hallo {$row['username']},\n\nheute läuft deine jährliche Bestätigung ab. Bitte verlängere hier, damit deine Subdomain(s) aktiv bleiben:\n$link\n\nDanke.\n";
    Util::sendMailStub($row['email'], 'DynDNS – Erinnerung (heute fällig)', $msg);

    // Deaktivieren
    $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([(int)$row['id']]);
}

// Am Tag nach Fälligkeit: löschen (DNS + Account)
$qx = $pdo->prepare("SELECT id, email, username FROM users WHERE annual_confirm_due < CURDATE()");
$qx->execute();
$usersToDelete = $qx->fetchAll();

foreach ($usersToDelete as $u) {
    $uid = (int)$u['id'];

    // DNS-Records ermitteln (vor dem Löschen!)
    $stmt = $pdo->prepare("SELECT r.hetzner_record_id_a, r.hetzner_record_id_aaaa FROM records r WHERE r.user_id=?");
    $stmt->execute([$uid]);
    $recs = $stmt->fetchAll();

    // Löschmail vor dem tatsächlichen Löschen senden
    $msg = "Hallo {$u['username']},\n\nleider hast du die jährliche Bestätigung nicht durchgeführt. Dein Account und die zugehörigen Subdomain-Einträge wurden nun gelöscht.\n\nFalls das ein Fehler ist, registriere dich bitte erneut.\n";
    Util::sendMailStub($u['email'], 'DynDNS – Account gelöscht', $msg);

    // Hetzner: Records löschen (Fehler ignorieren, aber loggen)
    foreach ($recs as $r) {
        foreach (['hetzner_record_id_a','hetzner_record_id_aaaa'] as $key) {
            $rid = $r[$key] ?? null;
            if ($rid) {
                try { $hz->deleteRecord($rid); } catch (Throwable $e) { error_log("Hetzner delete failed for $rid: ".$e->getMessage()); }
            }
        }
    }

    // Account löschen (CASCADE entfernt records in DB)
    try {
        $pdo->prepare("DELETE FROM users WHERE id=? LIMIT 1")->execute([$uid]);
    } catch (Throwable $e) {
        error_log("Delete user failed (id=$uid): ".$e->getMessage());
    }
}

echo "OK " . date('Y-m-d H:i:s') . PHP_EOL;
