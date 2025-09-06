<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
if (!Auth::isAdmin()) { http_response_code(403); die('forbidden'); }

$info = null;
$err  = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    Util::csrfCheck();
    if (($_POST['action'] ?? '')==='add_domain') {
        $name = strtolower(trim($_POST['name'] ?? ''));
        $zone = trim($_POST['zone_id'] ?? '');
        if ($name && $zone) {
            $stmt=$pdo->prepare('INSERT INTO domains (name, zone_id) VALUES (?,?)');
            $stmt->execute([$name, $zone]);
            $info = 'Domain hinzugefügt: ' . Util::html($name);
        }
    }
    if (($_POST['action'] ?? '')==='toggle_annual') {
        $uid = (int)($_POST['uid'] ?? 0);
        $stmt=$pdo->prepare('UPDATE users SET annual_confirm_due=DATE_ADD(CURDATE(), INTERVAL 50 YEAR) WHERE id=?');
        $stmt->execute([$uid]);
        $info = 'Jahrespflicht für User-ID '.$uid.' deaktiviert';
    }
    if (($_POST['action'] ?? '')==='delete_user') {
        $uid = (int)($_POST['uid'] ?? 0);
        try {
            // Hetzner-Cleanup
            $hz = new HetznerClient($env);
            $stmt=$pdo->prepare('SELECT hetzner_record_id_a, hetzner_record_id_aaaa FROM records WHERE user_id=?');
            $stmt->execute([$uid]);
            foreach ($stmt as $r) {
                foreach (['hetzner_record_id_a','hetzner_record_id_aaaa'] as $k) {
                    if (!empty($r[$k])) { try { $hz->deleteRecord($r[$k]); } catch (Throwable $e) { error_log("Hetzner delete failed for {$r[$k]}: ".$e->getMessage()); } }
                }
            }
            // User löschen
            $stmt=$pdo->prepare('DELETE FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$uid]);
            $info = 'Benutzer gelöscht: ID '.$uid.' (DNS bei Hetzner bereinigt)';
        } catch (Throwable $e) {
            $err = 'Löschen fehlgeschlagen: '.$e->getMessage();
        }
    }
}
$domains=$pdo->query('SELECT * FROM domains ORDER BY name')->fetchAll();
$users=$pdo->query('SELECT id,email,username,annual_confirm_due,role FROM users ORDER BY id DESC LIMIT 200')->fetchAll();
$subs=$pdo->query('SELECT r.id, r.sub_name, d.name AS domain_name, u.username, u.email FROM records r JOIN domains d ON d.id=r.domain_id JOIN users u ON u.id=r.user_id ORDER BY r.id DESC LIMIT 500')->fetchAll();
Util::csrfEnsure();
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
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
    <?php if ($info): ?><div class="card"><div class="alert ok"><?= $info ?></div></div><?php endif; ?>
    <?php if ($err): ?><div class="card"><div class="alert"><?= $err ?></div></div><?php endif; ?>

    <div class="card">
      <h3>Hauptdomain (Zone) hinzufügen</h3>
      <form class="form" method="post">
        <?= Util::csrfField() ?>
        <input type="hidden" name="action" value="add_domain">
        <label>Domain</label>
        <input class="input" name="name" placeholder="example.com" required>
        <label>Zone-ID</label>
        <input class="input" name="zone_id" placeholder="Hetzner Zone-ID" required>
        <div style="margin-top:10px"><button class="btn">Speichern</button></div>
      </form>
    </div>

    <div class="card">
      <h3>Vorhandene Domains</h3>
      <table class="table">
        <thead><tr><th>Domain</th><th>Zone-ID</th></tr></thead>
        <tbody>
          <?php foreach ($domains as $d): ?>
            <tr><td><?= Util::html($d['name']) ?></td><td><?= Util::html($d['zone_id']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Benutzer</h3>
      <table class="table">
        <thead><tr><th>ID</th><th>Username</th><th>E-Mail</th><th>Fällig</th><th>Aktion</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= Util::html($u['username']) ?></td>
              <td><?= Util::html($u['email']) ?></td>
              <td><?= Util::html($u['annual_confirm_due']) ?></td>
              <td style="display:flex;gap:8px;align-items:center">
                <form method="post" style="display:inline">
                  <?= Util::csrfField() ?>
                  <input type="hidden" name="action" value="toggle_annual">
                  <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                  <button class="btn secondary" type="submit">Jahrespflicht deaktivieren</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Benutzer wirklich löschen? DNS-Einträge werden bei Hetzner entfernt und der Account gelöscht.');">
                  <?= Util::csrfField() ?>
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                  <button class="btn danger" type="submit">Benutzer löschen</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Subdomains (mit Benutzer)</h3>
      <table class="table">
        <thead><tr><th>ID</th><th>FQDN</th><th>Benutzer</th><th>E-Mail</th></tr></thead>
        <tbody>
          <?php foreach ($subs as $s): 
            $fqdn = ($s['sub_name']==='') ? $s['domain_name'] : $s['sub_name'].'.'.$s['domain_name']; ?>
            <tr>
              <td><?= (int)$s['id'] ?></td>
              <td><?= Util::html($fqdn) ?></td>
              <td><?= Util::html($s['username']) ?></td>
              <td><?= Util::html($s['email']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
