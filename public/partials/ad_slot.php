<?php
$client   = $env['ADSENSE_CLIENT'] ?? '';
$slotId   = $slot ?? ($env['ADSENSE_SLOT_INDEX'] ?? '');
if ($client && $slotId):

  // 1) Fallback aus .env (falls vorhanden), sonst dynamisch aus DONATE_*
  $fallback = $env['ADSENSE_FALLBACK_HTML'] ?? '';

  if ($fallback === '') {
      $fallbackText = $env['DONATE_FALLBACK_TEXT'] ?? 'Leider blockieren Sie die Werbung. Wenn Sie uns anders unterstützen möchten, freuen wir uns über eine Spende:';
      $buttons = [];

      // Helper zum Erzeugen eines Buttons
      $mk = function(string $label = null, string $url = null, string $class = 'secondary') use (&$buttons) {
          if (!$label || !$url) return;
          $buttons[] =
            '<a class="btn '.$class.'" target="_blank" rel="noopener" href="'.
            htmlspecialchars($url, ENT_QUOTES).'">'.
            htmlspecialchars($label, ENT_QUOTES).'</a>';
      };

      // Bekannte Plattformen
      $mk('Ko-fi',   $env['DONATE_KOFI_URL']    ?? '');
      $mk('PayPal',  $env['DONATE_PAYPAL_URL']  ?? '');
      $mk('Patreon', $env['DONATE_PATREON_URL'] ?? '');

      // Optional: eigener Link (Label + URL)
      $mk($env['DONATE_CUSTOM_LABEL'] ?? '', $env['DONATE_CUSTOM_URL'] ?? '');

      $btnsHtml = $buttons
        ? '<p style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 0 0">'.implode('', $buttons).'</p>'
        : '';

      $fallback =
        '<h4>Unterstützen Sie unsere Projekte</h4>'.
        '<p class="small">'.htmlspecialchars($fallbackText, ENT_QUOTES).'</p>'.
        $btnsHtml;
  }
?>
<section class="hero" style="margin-top:18px">
  <div class="ad-wrap" data-adwrap>
    <ins class="adsbygoogle"
         style="display:block;min-height:120px;margin:12px 0"
         data-ad-client="<?= htmlspecialchars($client, ENT_QUOTES) ?>"
         data-ad-slot="<?= htmlspecialchars($slotId, ENT_QUOTES) ?>"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>

    <!-- Anzeige rendern -->
    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>

    <!-- Blocker-/DNS-Fehler-Erkennung + Fallback -->
    <script>
    (function(){
      var wrap = document.currentScript && document.currentScript.parentElement;
      if (!wrap) return;

      // Aus PHP generierter Fallback-HTML-String
      var fallbackHtml = <?= json_encode($fallback, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

      function blocked(){
        if (window.__adsScriptError) return true;                    // Script-Ladefehler (z.B. DNS-Block)
        if (typeof window.adsbygoogle === 'undefined') return true;  // Script nicht vorhanden
        var ins = wrap.querySelector('ins.adsbygoogle');
        if (!ins) return true;                                       // von Blocker entfernt
        var cs = getComputedStyle(ins);
        if (cs.display === 'none' || cs.visibility === 'hidden') return true;
        if (ins.clientWidth === 0 || ins.clientHeight === 0) return true;
        return false;
      }

      function swapIfBlocked(){
        if (blocked()) {
          var box = document.createElement('div');
          box.className = 'card';
          box.innerHTML = fallbackHtml;
          wrap.replaceWith(box);
        }
      }

      setTimeout(swapIfBlocked, 600);
      setTimeout(swapIfBlocked, 1500);
      window.addEventListener('load', function(){ setTimeout(swapIfBlocked, 2500); });
    })();
    </script>
  </div>
</section>
<?php endif; ?>
