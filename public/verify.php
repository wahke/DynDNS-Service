<?php
require_once __DIR__ . '/../bootstrap.php';

$token = $_GET['t'] ?? '';
$ok = false; $msg = '';

if ($token) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email_verification_token=? LIMIT 1');
    $stmt->execute([$token]);
    $u = $stmt->fetch();
    if ($u) {
        $pdo->prepare('UPDATE users SET is_active=1, email_verified_at=NOW(), email_verification_token=NULL, updated_at=NOW() WHERE id=?')->execute([(int)$u['id']]);
        $ok = true;
        $msg = 'Danke! Deine E-Mail wurde bestätigt und dein Konto ist jetzt aktiv.';
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
  <title>E-Mail bestätigen</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
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
      <h3>E-Mail-Bestätigung</h3>
      <div class="alert <?= $ok ? 'ok' : '' ?>"><?= Util::html($msg) ?></div>
      <a class="btn" href="/login.php">Zum Login</a>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
