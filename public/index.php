<?php require_once __DIR__ . '/../bootstrap.php'; ?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= Util::brandName($env) ?> – DynDNS</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
  <?php include __DIR__ . '/partials/ads_head.php'; ?>
</head>
<body>
  <header class="header">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:16px">
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <nav class="nav"><a href="/register.php">Registrierung</a><a href="/login.php">Login</a></nav>
    </div>
  </header>

<main class="main"><div class="container">

  <section class="hero">
    <h1>Eigene DynDNS-Plattform für deine Subdomains</h1>
    <p>Verbinde deine wechselnde IP (IPv4/IPv6) mit einer stabilen Subdomain. Updates laufen automatisiert via FRITZ!Box über unseren Endpoint, wir pflegen die DNS-Einträge bei Hetzner.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn" href="/register.php">Jetzt registrieren</a>
      <a class="btn secondary" href="/login.php">Einloggen</a>
    </div>
  </section>

  <?php $slot = $GLOBALS['env']['ADSENSE_SLOT_INDEX'] ?? ''; include __DIR__ . '/partials/ad_slot.php'; ?>

  <section class="grid two" style="margin-top:18px">
    <div class="card">
      <h3>Wie funktioniert's?</h3>
      <p>Du registrierst dich, wählst eine Hauptdomain (Zone) und trägst deine Wunsch-Subdomain ein. Deine FRITZ!Box ruft bei jeder IP-Änderung unsere Update-URL auf.</p>
    </div>
    <div class="card">
      <h3>FRITZ!Box kompatibel</h3>
      <p>Nutze den Anbieter <em>„Benutzerdefiniert“</em> und die bereitgestellte Update-URL samt Platzhaltern.
      </p>
    </div>
  </section>

  </div></main>
  <?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
