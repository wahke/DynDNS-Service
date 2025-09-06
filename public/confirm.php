<?php
require_once __DIR__ . '/../bootstrap.php';

$token = $_GET['t'] ?? '';
$ok = false; $msg = '';

if ($token) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE annual_confirm_token=? LIMIT 1');
    $stmt->execute([$token]);
    $u = $stmt->fetch();
    if ($u) {
        $due = (new DateTimeImmutable('+1 year'))->format('Y-m-d');
        $pdo->prepare('UPDATE users SET annual_confirm_due=?, annual_confirmed_at=NOW(), is_active=1, annual_confirm_token=NULL WHERE id=?')->execute([$due, (int)$u['id']]);
        $ok = true;
        $msg = 'Danke! Deine Nutzung wurde um 1 Jahr verlängert.';
    } else {
        $msg = 'Link ungültig oder bereits verwendet.';
    }
} else {
    $msg = 'Kein Token angegeben.';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jährliche Bestätigung</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
</head>
<body>
  <header class="header">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:16px">
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <nav class="nav"><a href="/dashboard.php">Dashboard</a></nav>
    </div>
  </header>
  <main class="main"><div class="container">
    <div class="card">
      <h3>Jährliche Bestätigung</h3>
      <div class="alert <?= $ok ? 'ok' : '' ?>"><?= Util::html($msg) ?></div>
      <a class="btn" href="/dashboard.php">Zurück zum Dashboard</a>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
