<?php
$brand = Util::brandName($env);
$logo  = Util::brandLogoUrl($env);
?>
<a class="brand" href="/">
  <?php if ($logo): ?>
    <img src="<?= Util::html($logo) ?>" alt="<?= Util::html($brand) ?>" style="height:24px;margin-right:8px;vertical-align:middle">
  <?php else: ?>
    <span class="dot"></span>
  <?php endif; ?>
  <?= Util::html($brand) ?>
</a>
