<?php
$client = $env['ADSENSE_CLIENT'] ?? '';
$slot = $slot ?? '';
if ($client && $slot):
?>
<ins class="adsbygoogle"
     style="display:block;margin:12px 0"
     data-ad-client="<?= htmlspecialchars($client) ?>"
     data-ad-slot="<?= htmlspecialchars($slot) ?>"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
<?php endif; ?>
