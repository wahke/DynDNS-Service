<?php
$copy = Util::footerCopyright($env);
$links = Util::brandLinks($env);
?>
<footer class="footer"><div class="container small">
  <?= Util::html($copy) ?>
  <?php if ($links['imprint'] || $links['privacy'] || $links['terms']): ?>
    ·
    <?php if ($links['imprint']): ?><a href="<?= Util::html($links['imprint']) ?>" target="_blank" rel="nofollow noopener">Impressum</a><?php endif; ?>
    <?php if ($links['privacy']): ?><?= $links['imprint'] ? ' · ' : '' ?><a href="<?= Util::html($links['privacy']) ?>" target="_blank" rel="nofollow noopener">Datenschutz</a><?php endif; ?>
    <?php if ($links['terms']): ?><?= ($links['imprint']||$links['privacy']) ? ' · ' : '' ?><a href="<?= Util::html($links['terms']) ?>" target="_blank" rel="nofollow noopener">AGB</a><?php endif; ?>
  <?php endif; ?>
</div></footer>
