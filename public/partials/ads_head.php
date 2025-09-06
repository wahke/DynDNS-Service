<?php if (!empty($env['ADSENSE_CLIENT'])): ?>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($env['ADSENSE_CLIENT']) ?>" crossorigin="anonymous"></script>
<?php endif; ?>
