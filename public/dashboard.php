<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
$uid = (int)$_SESSION['uid'];

$u = $pdo->query('SELECT * FROM users WHERE id='.$uid)->fetch();

// Handle manual update per record
if (($_SERVER['REQUEST_METHOD']==='POST') && ($_POST['action'] ?? '')==='manual_update_record') {
    Util::csrfCheck();
    $recordId = (int)($_POST['record_id'] ?? 0);
    [$ipv4,$ipv6]=Util::clientIp();
    $stmt = $pdo->prepare('SELECT r.*, d.name AS domain_name, d.zone_id AS domain_zone_id FROM records r JOIN domains d ON d.id=r.domain_id WHERE r.id=? AND r.user_id=?');
    $stmt->execute([$recordId, $uid]);
    $rec = $stmt->fetch();
    if ($rec) {
        $hz = new HetznerClient($env);
        $namePart = ($rec['sub_name']==='') ? '@' : $rec['sub_name'];
        $ttl = (int)$rec['ttl'];
        if ($rec['hetzner_record_id_a'] && $ipv4 && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $hz->updateRecordFull($rec['hetzner_record_id_a'], $rec['domain_zone_id'], $namePart, 'A', $ipv4, $ttl);
            $pdo->prepare('UPDATE records SET last_ipv4=?, updated_at=NOW() WHERE id=?')->execute([$ipv4, $rec['id']]);
            $msgIPv4 = 'IPv4 aktualisiert: '.$ipv4;
        }
        if ($rec['hetzner_record_id_aaaa'] && $ipv6 && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hz->updateRecordFull($rec['hetzner_record_id_aaaa'], $rec['domain_zone_id'], $namePart, 'AAAA', $ipv6, $ttl);
            $pdo->prepare('UPDATE records SET last_ipv6=?, updated_at=NOW() WHERE id=?')->execute([$ipv6, $rec['id']]);
            $msgIPv6 = 'IPv6 aktualisiert: '.$ipv6;
        }
    }
}

// Load records
$records = $pdo->prepare('SELECT r.*, d.name AS domain_name FROM records r JOIN domains d ON d.id=r.domain_id WHERE r.user_id=? ORDER BY r.id DESC');
$records->execute([$uid]);
$rows = $records->fetchAll();

$updateUrlTpl = Util::baseUrl($env) . '/dyndns/update.php?hostname=<domain>&myip=<ipaddr>&myipv6=<ip6addr>&username=<username>&password=<pass>';
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
  <?php include __DIR__ . '/partials/ads_head.php'; ?>
</head>
<body>
  <header class="header">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:16px">
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <nav class="nav">
        <a href="/">Start</a>
        <a href="/dashboard.php">Dashboard</a>
        <a href="/subdomains.php">Subdomains</a>
        <?php if (Auth::isAdmin()): ?><a href="/admin.php">Admin</a><?php endif; ?>
        <a href="/logout.php">Logout</a>
      </nav>
    </div>
  </header>
  <main class="main"><div class="container grid">
    <div class="card">
      <h3>Willkommen, <?= Util::html($_SESSION['username'] ?? '') ?></h3>
      <p><strong>DynDNS Benutzername:</strong> <?= Util::html($u['username']) ?><br>
         <strong>DynDNS Token:</strong> <?= Util::html($u['ddns_token']) ?></p>
      <p><strong>FRITZ!Box Update-URL (benutzerdefiniert):</strong></p>
      <pre class="code"><?= Util::html($updateUrlTpl) ?></pre>
      <p class="small">In der FRITZ!Box trägst du je Subdomain ihren Hostname ein (z. B. <code>home.example.com</code>).</p>
      <?php if (!empty($msgIPv4) || !empty($msgIPv6)): ?>
        <div class="alert ok">
          <?= isset($msgIPv4) ? Util::html($msgIPv4) . '<br>' : '' ?>
          <?= isset($msgIPv6) ? Util::html($msgIPv6) : '' ?>
        </div>
      <?php endif; ?>
      <?php $slot = $env['ADSENSE_SLOT_DASHBOARD'] ?? ''; include __DIR__ . '/partials/ad_slot.php'; ?>
    </div>

    <div class="card">
      <h3>Deine Subdomains</h3>
      <table class="table">
        <thead><tr><th>FQDN</th><th>IPv4</th><th>IPv6</th><th>Aktion</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): 
            $fqdn = ($r['sub_name']==='') ? $r['domain_name'] : $r['sub_name'].'.'.$r['domain_name']; ?>
            <tr>
              <td><?= Util::html($fqdn) ?></td>
              <td><?= Util::html($r['last_ipv4'] ?? '') ?></td>
              <td><?= Util::html($r['last_ipv6'] ?? '') ?></td>
              <td>
                <form method="post" style="display:inline">
                  <?= Util::csrfField() ?>
                  <input type="hidden" name="action" value="manual_update_record">
                  <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                  <button class="btn secondary" type="submit">Jetzt aktualisieren</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="margin-top:10px"><a class="btn" href="/subdomains.php">+ Weitere Subdomain anlegen</a></div>
    </div>

    <div class="card">
      <h3>Jährliche Bestätigung</h3>
      <p>Fällig am: <strong><?= Util::html($u['annual_confirm_due']) ?></strong></p>
      <form method="post" action="/dashboard.php">
        <?= Util::csrfField() ?>
        <input type="hidden" name="action" value="renew">
        <button class="btn ok">Jetzt bestätigen (1 Jahr verlängern)</button>
      </form>
      <?php
      if (($_SERVER['REQUEST_METHOD']==='POST') && ($_POST['action'] ?? '')==='renew') {
          Util::csrfCheck();
          $due = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
          $pdo->prepare('UPDATE users SET annual_confirm_due=?, annual_confirmed_at=NOW(), is_active=1 WHERE id=?')->execute([$due, $uid]);
          echo '<div class="alert ok">Erfolgreich bis '.Util::html($due).' verlängert.</div>';
      }
      ?>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
