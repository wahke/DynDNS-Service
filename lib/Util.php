<?php
class Util {
    public static function loadEnv(string $path): array {
        if (!is_file($path)) return [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$k,$v] = array_pad(explode('=', $line, 2), 2, '');
            $env[trim($k)] = trim($v);
        }
        return $env;
    }

    public static function now(): string { return (new DateTimeImmutable())->format('Y-m-d H:i:s'); }

    public static function randToken(int $len = 64): string {
        return bin2hex(random_bytes((int)($len/2)));
    }

    public static function clientIp(): array {
        $ipv4 = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ipv4 && str_contains($ipv4, ',')) $ipv4 = trim(explode(',', $ipv4)[0]);
        $ipv6 = $_SERVER['HTTP_X_REAL_IP'] ?? null; // optional second source
        return [$ipv4, $ipv6];
    }

    public static function redirect(string $path) {
        header('Location: ' . $path); exit;
    }

    public static function baseUrl(array $env): string { return rtrim($env['BASE_URL'] ?? '', '/'); }

    public static function csrfEnsure(): void {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = self::randToken(32);
    }

    public static function csrfField(): string {
        self::csrfEnsure();
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
    }

    public static function csrfCheck(): void {
        if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
            http_response_code(400); die('CSRF ungültig');
        }
    }

    public static function html(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    // ---------- Branding helpers ----------
    public static function brandName(array $env): string {
        $n = trim($env['BRAND_NAME'] ?? '');
        return $n !== '' ? $n : 'DynDNS';
    }
    public static function brandLogoUrl(array $env): string {
        return trim($env['BRAND_LOGO_URL'] ?? '');
    }
    public static function supportEmail(array $env): string {
        return trim($env['SUPPORT_EMAIL'] ?? ($env['MAIL_FROM'] ?? 'support@example.tld'));
    }
    public static function footerCopyright(array $env): string {
        $year = date('Y');
        $bn = self::brandName($env);
        $tpl = $env['FOOTER_COPYRIGHT'] ?? "© {$year} {$bn} · Hetzner DNS API · PHP · MySQL";
        return $tpl;
    }
    public static function brandLinks(array $env): array {
        return [
            'imprint' => trim($env['BRAND_IMPRINT_URL'] ?? ''),
            'privacy' => trim($env['BRAND_PRIVACY_URL'] ?? ''),
            'terms'   => trim($env['BRAND_TERMS_URL'] ?? ''),
        ];
    }

    // ---------- Mail helpers ----------
    public static function sendMailStub(string $to, string $subject, string $body): void {
        // Plaintext fallback
        $env = $GLOBALS['env'] ?? [];
        $fromName = $env['MAIL_FROM_NAME'] ?? self::brandName($env);
        $fromAddr = $env['MAIL_FROM'] ?? 'noreply@example.tld';
        $from = sprintf('%s <%s>', self::encodeHeader($fromName), $fromAddr);
        $headers = [];
        $headers[] = 'From: ' . $from;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $subject = str_replace(["\r", "\n"], '', $subject);
        @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public static function sendMailHtml(string $to, string $subject, string $textBody, string $htmlBody): void {
        $env = $GLOBALS['env'] ?? [];
        $fromName = $env['MAIL_FROM_NAME'] ?? self::brandName($env);
        $fromAddr = $env['MAIL_FROM'] ?? 'noreply@example.tld';

        $boundary = 'b_' . bin2hex(random_bytes(8));
        $from = sprintf('%s <%s>', self::encodeHeader($fromName), $fromAddr);
        $headers = [];
        $headers[] = 'From: ' . $from;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $subject = str_replace(["\r", "\n"], '', $subject);

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    public static function emailTemplate(array $env, string $title, string $contentHtml, ?string $ctaHref = null, ?string $ctaLabel = null): string {
        $brand = self::brandName($env);
        $logo  = self::brandLogoUrl($env);
        $links = self::brandLinks($env);
        $color = trim($env['EMAIL_PRIMARY_COLOR'] ?? '#4e7be6');
        $support = self::supportEmail($env);

        $btn = '';
        if ($ctaHref && $ctaLabel) {
            $btn = '<div style="margin:24px 0;"><a href="'.self::html($ctaHref).'" style="background:'.$color.';color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;display:inline-block">'.$ctaLabel.'</a></div>';
        }

        $logoHtml = $logo !== ''
            ? '<img src="'.self::html($logo).'" alt="'.self::html($brand).'" style="height:40px;display:block">'
            : '<div style="font-weight:700;font-size:18px;color:#0b1220">'.$brand.'</div>';

        $linksHtml = [];
        if (!empty($links['imprint'])) $linksHtml[] = '<a href="'.self::html($links['imprint']).'" style="color:#77809a;text-decoration:none">Impressum</a>';
        if (!empty($links['privacy'])) $linksHtml[] = '<a href="'.self::html($links['privacy']).'" style="color:#77809a;text-decoration:none">Datenschutz</a>';
        if (!empty($links['terms']))   $linksHtml[] = '<a href="'.self::html($links['terms']).'" style="color:#77809a;text-decoration:none">AGB</a>';

        $linksLine = '';
        if ($linksHtml) $linksLine = ' · ' . implode(' · ', $linksHtml);

        $year = date('Y');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'.
               '<title>'.self::html($title).'</title></head>'.
               '<body style="margin:0;background:#f3f5fa;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0b1220">'.
               '<div style="max-width:640px;margin:0 auto;padding:24px">'.
                 '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">'.
                 '<tr><td style="padding:12px 0">'.$logoHtml.'</td></tr>'.
                 '<tr><td style="background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.05);padding:24px">'.
                   '<h1 style="font-size:20px;margin:0 0 12px 0">'.$title.'</h1>'.
                   '<div style="font-size:14px;line-height:1.6;color:#0b1220">'.$contentHtml.$btn.'</div>'.
                 '</td></tr>'.
                 '<tr><td style="padding:16px 8px;color:#77809a;font-size:12px;line-height:1.6;text-align:center">'.
                   '© '.$year.' '.$brand.$linksLine.' · Support: <a href="mailto:'.self::html($support).'" style="color:#77809a;text-decoration:none">'.self::html($support).'</a>'.
                 '</td></tr>'.
                 '</table>'.
               '</div>'.
               '</body></html>';
    }

    private static function encodeHeader(string $text): string {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /** Check subdomain label against blacklist from .env (SUBDOMAIN_BLACKLIST) */
    public static function isSubBlacklisted(string $sub, array $env): bool {
        if ($sub === '') return false; // Root (@) nicht blockieren
        $raw = strtolower($env['SUBDOMAIN_BLACKLIST'] ?? '');
        if ($raw === '') return false;
        $parts = preg_split('~[,\s;]+~', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $sub = strtolower($sub);
        return in_array($sub, $parts, true);
    }
}
