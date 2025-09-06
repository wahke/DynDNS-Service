<?php if (!empty($env['ADSENSE_CLIENT'])): ?>
<script async
        src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($env['ADSENSE_CLIENT'], ENT_QUOTES) ?>"
        crossorigin="anonymous"
        onerror="window.__adsScriptError = true"></script>
<script>window.adsbygoogle = window.adsbygoogle || [];</script>
<?php endif; ?>
