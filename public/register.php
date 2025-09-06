<?php
require_once __DIR__ . '/../bootstrap.php';

$domains = $pdo->query('SELECT id, name, zone_id FROM domains WHERE is_active=1 ORDER BY name')->fetchAll();

$errs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Util::csrfCheck();
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $domainId = (int)($_POST['domain_id'] ?? 0);
    $sub = strtolower(trim($_POST['sub'] ?? ''));
    [$ipv4, $ipv6] = Util::clientIp();

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'E-Mail ungültig';
    if (!preg_match('~^[a-zA-Z0-9_-]{3,32}$~', $username)) $errs[] = 'Benutzername ungültig';
    if (strlen($password) < 8) $errs[] = 'Passwort zu kurz';
    if (!preg_match('~^[a-z0-9-]{0,63}$~', $sub)) $errs[] = 'Subdomain ungültig';
    if ($sub !== '' && Util::isSubBlacklisted($sub, $env)) $errs[] = 'Diese Subdomain ist gesperrt. Bitte wähle eine andere.';

    $stmt = $pdo->prepare('SELECT * FROM domains WHERE id=? AND is_active=1');
    $stmt->execute([$domainId]);
    $domain = $stmt->fetch();
    if (!$domain) $errs[] = 'Hauptdomain nicht gefunden';

    if (!$errs) {
        $pdo->beginTransaction();
        try {
            $uid = Auth::register($pdo, $env, $email, $username, $password);
            $user = $pdo->query('SELECT username, ddns_token, email_verification_token FROM users WHERE id='.(int)$uid.' LIMIT 1')->fetch();

            // DNS initial
            $hz = new HetznerClient($env);
            $ttl = (int)($env['TTL_DEFAULT'] ?? 300);
            if ($ttl < 60) $ttl = 60;
            if ($ttl > 86400) $ttl = 86400;
            $namePart = $sub === '' ? '@' : $sub;
            $recA = null; $recAAAA = null;
            if ($ipv4 && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $recA = $hz->createRecord($domain['zone_id'], $namePart, 'A', $ipv4, $ttl); }
            if ($ipv6 && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { $recAAAA = $hz->createRecord($domain['zone_id'], $namePart, 'AAAA', $ipv6, $ttl); }
            $stmt = $pdo->prepare('INSERT INTO records (user_id, domain_id, sub_name, hetzner_record_id_a, hetzner_record_id_aaaa, last_ipv4, last_ipv6, ttl, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute([$uid, $domain['id'], $sub, $recA['id'] ?? null, $recAAAA['id'] ?? null, $ipv4, $ipv6, $ttl]);
            $pdo->commit();

            // E-Mails (HTML)
            $fqdn = ($sub === '' ? $domain['name'] : $sub . '.' . $domain['name']);
            $updateUrl = Util::baseUrl($env) . '/dyndns/update.php?hostname=<domain>&myip=<ipaddr>&myipv6=<ip6addr>&username=<username>&password=<pass>';

            $verifyLink = Util::baseUrl($env) . '/verify.php?t=' . urlencode($user['email_verification_token']);
            $textVerify = "Hallo $username,\n\nbitte bestätige deine E-Mail-Adresse, um dein Konto zu aktivieren:\n$verifyLink\n\nViele Grüße\n".Util::brandName($env);
            $htmlVerifyContent = '<p>Hallo '.Util::html($username).',</p><p>bitte bestätige deine E-Mail-Adresse, um dein Konto zu aktivieren.</p><p><a href="'.Util::html($verifyLink).'">'.Util::html($verifyLink).'</a></p>';
            $htmlVerify = Util::emailTemplate($env, 'E-Mail bestätigen', $htmlVerifyContent, $verifyLink, 'E-Mail bestätigen');
            Util::sendMailHtml($email, 'Bitte E-Mail bestätigen', $textVerify, $htmlVerify);

            $textCreds = "Hallo $username,\n\ndein DynDNS-Zugang wurde erstellt – bitte bestätige zuerst deine E-Mail.\n\nSubdomain: $fqdn\nBenutzername: {$user['username']}\nDynDNS-Token: {$user['ddns_token']}\n\nFRITZ!Box Update-URL:\n$updateUrl\n";
            $htmlCredsContent = '<p>Hallo '.Util::html($username).',</p>'.
                                '<p>dein DynDNS-Zugang wurde erstellt – bitte bestätige zuerst deine E-Mail.</p>'.
                                '<p><strong>Subdomain:</strong> '.Util::html($fqdn).'<br>'.
                                '<strong>Benutzername:</strong> '.Util::html($user['username']).'<br>'.
                                '<strong>DynDNS-Token:</strong> '.Util::html($user['ddns_token']).'</p>'.
                                '<p><strong>FRITZ!Box – Update-URL:</strong><br><code>'.Util::html($updateUrl).'</code></p>';
            $htmlCreds = Util::emailTemplate($env, 'Zugangsdaten', $htmlCredsContent, null, null);
            Util::sendMailHtml($email, 'Deine Zugangsdaten (noch nicht aktiviert)', $textCreds, $htmlCreds);

?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrierung – Bestätigung erforderlich</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
  <?php include __DIR__ . '/partials/ads_head.php'; ?>
</head>
<body>
  <header class="header">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:16px">
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <nav class="nav"><a href="/">Start</a><a href="/login.php">Login</a></nav>
    </div>
  </header>
  <main class="main"><div class="container">
    <div class="card">
      <h3>Registrierung erfolgreich – bitte E-Mail bestätigen</h3>
      <p>Wir haben dir eine E-Mail an <strong><?= Util::html($email) ?></strong> geschickt. Klicke auf den Bestätigungslink, um dein Konto zu aktivieren.</p>
      <div style="display:flex;gap:10px"><a class="btn" href="/login.php">Zum Login</a></div>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body></html>
<?php
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errs[] = 'Fehler: ' . $e->getMessage();
        }
    }
}

Util::csrfEnsure();
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrierung</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
  <?php include __DIR__ . '/partials/ads_head.php'; ?>
</head>
<body>
  <header class="header">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:16px">
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <nav class="nav"><a href="/">Start</a><a href="/register.php">Registrierung</a><a href="/login.php">Login</a></nav>
    </div>
  </header>
  <main class="main"><div class="container">
    <div class="grid two">
      <div class="card">
        <h3>Registrieren</h3>
        <?php if (!empty($errs)): ?>
          <div class="alert"><?php foreach ($errs as $e) echo '<div>'.Util::html($e).'</div>'; ?></div>
        <?php endif; ?>
        <form class="form" method="post">
          <?= Util::csrfField() ?>
          <label>E-Mail</label>
          <input class="input" type="email" name="email" required>
          <label>Benutzername</label>
          <input class="input" name="username" required>
          <label>Passwort</label>
          <input class="input" type="password" name="password" required>
          <div class="row">
            <div>
              <label>Hauptdomain</label>
              <select name="domain_id" class="input" required>
                <option value="">– wählen –</option>
                <?php foreach ($domains as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= Util::html($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Subdomain (leer für @)</label>
              <input class="input" name="sub" placeholder="z.B. home">
            </div>
          </div>
          <div style="margin-top:10px"><button class="btn" type="submit">Anlegen</button></div>
        </form>
      </div>
      <div class="card">
        <h3>Hinweis</h3>
        <p class="small">Du erhältst eine E-Mail zur Bestätigung. Erst danach wird dein Konto aktiviert. Bestimmte Subdomains können vom Administrator gesperrt sein.</p>
      </div>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
