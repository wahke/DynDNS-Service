<?php
require_once __DIR__ . '/../bootstrap.php';
function mask($s, $keep = 6) {
  $s = (string)$s;
  if ($s === '') return '';
  $len = strlen($s);
  if ($len <= $keep) return str_repeat('•', $len);
  return str_repeat('•', max(0,$len-$keep)) . substr($s, -$keep);
}
$client = $env['ADSENSE_CLIENT'] ?? '';
$slotIndex = $env['ADSENSE_SLOT_INDEX'] ?? '';
$slotDash  = $env['ADSENSE_SLOT_DASHBOARD'] ?? '';
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ads Debug</title>
  <link rel="stylesheet" href="/assets/style.css?v=1">
  <?php include __DIR__ . '/partials/ads_head.php'; ?>
</head>
<body>
  <main class="main"><div class="container">
    <div class="card">
      <h3>AdSense – Debug</h3>
      <ul class="small">
        <li><b>ADSENSE_CLIENT</b>: <code><?= htmlspecialchars(mask($client)) ?></code></li>
        <li><b>ADSENSE_SLOT_INDEX</b>: <code><?= htmlspecialchars($slotIndex ?: '—') ?></code></li>
        <li><b>ADSENSE_SLOT_DASHBOARD</b>: <code><?= htmlspecialchars($slotDash ?: '—') ?></code></li>
      </ul>
      <?php if (!$client): ?><div class="alert">Kein <b>ADSENSE_CLIENT</b> gesetzt → keine Anzeigen.</div><?php endif; ?>
    </div>

    <div class="card">
      <h3>Test-Slots</h3>
      <div>
        <h4>Index</h4>
        <?php $slot = $slotIndex; include __DIR__ . '/partials/ad_slot.php'; ?>
      </div>
      <div>
        <h4>Dashboard</h4>
        <?php $slot = $slotDash; include __DIR__ . '/partials/ad_slot.php'; ?>
      </div>
    </div>
  </div></main>
</body>
</html>
