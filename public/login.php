<?php
require_once __DIR__ . '/../bootstrap.php';

$err = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Util::csrfCheck();
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1');
    $stmt->execute([$login, $login]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($password, $u['password_hash'])) {
        $err = 'Login fehlgeschlagen';
    } else {
        if ((int)$u['is_active'] !== 1) {
            $err = 'Bitte bestätige zuerst deine E-Mail.';
            $_SESSION['pending_verify_user_id'] = (int)$u['id'];
        } else {
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['role'] = $u['role'];
            $_SESSION['username'] = $u['username'];
            Util::redirect('/dashboard.php');
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['action'] ?? '') === 'resend_verify')) {
    Util::csrfCheck();
    $uid = (int)($_SESSION['pending_verify_user_id'] ?? 0);
    if ($uid) {
        $stmt = $pdo->prepare('SELECT id, email, username, email_verification_token FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if ($u) {
            $token = $u['email_verification_token'] ?: Util::randToken(64);
            if (!$u['email_verification_token']) {
                $pdo->prepare('UPDATE users SET email_verification_token=?, updated_at=NOW() WHERE id=?')->execute([$token, $uid]);
            }
            $link = Util::baseUrl($env) . '/verify.php?t=' . urlencode($token);
            $text = "Hallo {$u['username']},\n\nbitte bestätige deine E-Mail-Adresse:\n$link\n";
            $htmlContent = '<p>Hallo '.Util::html($u['username']).',</p><p>bitte bestätige deine E-Mail-Adresse.</p><p><a href="'.Util::html($link).'">'.Util::html($link).'</a></p>';
            $html = Util::emailTemplate($env, 'E-Mail bestätigen (erneut)', $htmlContent, $link, 'E-Mail bestätigen');
            Util::sendMailHtml($u['email'], 'E-Mail bestätigen (erneut)', $text, $html);
            $info = 'Bestätigungslink erneut gesendet.';
        }
    }
}

Util::csrfEnsure();
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
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
        <h3>Einloggen</h3>
        <?php if (!empty($err)): ?><div class="alert"><?= Util::html($err) ?></div><?php endif; ?>
        <?php if (!empty($info)): ?><div class="alert ok"><?= Util::html($info) ?></div><?php endif; ?>
        <form class="form" method="post">
          <?= Util::csrfField() ?>
          <label>E-Mail oder Benutzername</label>
          <input class="input" name="login" required>
          <label>Passwort</label>
          <input class="input" type="password" name="password" required>
          <div style="margin-top:10px"><button class="btn" type="submit">Login</button></div>
        </form>
        <?php if (!empty($_SESSION['pending_verify_user_id'])): ?>
          <form method="post" style="margin-top:10px">
            <?= Util::csrfField() ?>
            <input type="hidden" name="action" value="resend_verify">
            <button class="btn secondary" type="submit">Bestätigungslink erneut senden</button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card">
        <h3>Neu hier?</h3>
        <p>Lege dir jetzt deinen Zugang an. Du erhältst eine E-Mail zur Bestätigung.</p>
        <a class="btn" href="/register.php">Registrieren</a>
      </div>
    </div>
  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
