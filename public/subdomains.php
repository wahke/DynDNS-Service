<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$uid = (int)$_SESSION['uid'];
$domains = $pdo->query('SELECT id, name, zone_id FROM domains WHERE is_active=1 ORDER BY name')->fetchAll();

$ttlDefault = (int)($env['TTL_DEFAULT'] ?? 300);
if ($ttlDefault < 60) $ttlDefault = 60;
if ($ttlDefault > 86400) $ttlDefault = 86400;

$errs = [];
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Util::csrfCheck();
    $domainId = (int)($_POST['domain_id'] ?? 0);
    $sub = strtolower(trim($_POST['sub'] ?? ''));
    $ttl = $ttlDefault; // admin-only; users cannot change
    [$ipv4, $ipv6] = Util::clientIp();

    if (!preg_match('~^[a-z0-9-]{0,63}$~', $sub)) $errs[] = 'Subdomain ungültig';
    if ($sub !== '' && Util::isSubBlacklisted($sub, $env)) $errs[] = 'Diese Subdomain ist gesperrt. Bitte wähle eine andere.';

    $stmt = $pdo->prepare('SELECT * FROM domains WHERE id=? AND is_active=1');
    $stmt->execute([$domainId]);
    $domain = $stmt->fetch();
    if (!$domain) $errs[] = 'Hauptdomain nicht gefunden';

    if (!$errs) {
        $pdo->beginTransaction();
        try {
            $hz = new HetznerClient($env);
            $namePart = $sub === '' ? '@' : $sub;

            $recA = null; $recAAAA = null;
            if ($ipv4 && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $recA = $hz->createRecord($domain['zone_id'], $namePart, 'A', $ipv4, $ttl);
            }
            if ($ipv6 && filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $recAAAA = $hz->createRecord($domain['zone_id'], $namePart, 'AAAA', $ipv6, $ttl);
            }

            $stmt = $pdo->prepare('INSERT INTO records (user_id, domain_id, sub_name, hetzner_record_id_a, hetzner_record_id_aaaa, last_ipv4, last_ipv6, ttl, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute([$uid, $domain['id'], $sub, $recA['id'] ?? null, $recAAAA['id'] ?? null, $ipv4, $ipv6, $ttl]);

            $pdo->commit();
            $fqdn = ($sub === '' ? $domain['name'] : $sub . '.' . $domain['name']);
            $msg = 'Subdomain angelegt: ' . Util::html($fqdn);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errs[] = 'Fehler: ' . $e->getMessage();
        }
    }
}

$records = $pdo->prepare('SELECT r.*, d.name AS domain_name, d.zone_id AS domain_zone_id FROM records r JOIN domains d ON d.id=r.domain_id WHERE r.user_id=? ORDER BY r.id DESC');
$records->execute([$uid]);
$rows = $records->fetchAll();

Util::csrfEnsure();
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subdomains verwalten</title>
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
      <h3>Neue Subdomain anlegen</h3>
      <?php if ($msg): ?><div class="alert ok"><?= $msg ?></div><?php endif; ?>
      <?php if ($errs): ?><div class="alert"><?php foreach ($errs as $e) echo '<div>'.Util::html($e).'</div>'; ?></div><?php endif; ?>
      <form class="form" method="post">
        <?= Util::csrfField() ?>
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
            <input class="input" name="sub" placeholder="z.B. vpn">
          </div>
        </div>
        <p class="small">Hinweis: Manche Subdomains sind aus Sicherheitsgründen gesperrt.</p>
        <div style="margin-top:10px"><button class="btn" type="submit">Anlegen</button></div>
      </form>
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
                <form method="post" action="/dashboard.php" style="display:inline">
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
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
