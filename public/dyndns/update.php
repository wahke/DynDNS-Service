<?php
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

function resp(string $s){ echo $s; exit; }

// Accept auth via query or HTTP Basic
$user = $_GET['username'] ?? ($_SERVER['PHP_AUTH_USER'] ?? '');
$pass = $_GET['password'] ?? ($_SERVER['PHP_AUTH_PW'] ?? '');

$hostname = strtolower(trim($_GET['hostname'] ?? ''));
$myip4 = trim($_GET['myip'] ?? '');
$myip6 = trim($_GET['myipv6'] ?? '');

if ($hostname === '') resp('notfqdn');
if ($user === '' || $pass === '') resp('badauth');

// Find user by username + token
$stmt = $pdo->prepare('SELECT * FROM users WHERE username=? LIMIT 1');
$stmt->execute([$user]);
$u = $stmt->fetch();
if (!$u) resp('badauth');
if (!hash_equals($u['ddns_token'], $pass)) resp('badauth');
if ((int)$u['is_active'] !== 1) resp('nochg'); // inactive (not verified or expired)

// Determine domain and sub by matching known domains
$domains = $pdo->query('SELECT * FROM domains WHERE is_active=1')->fetchAll();
$matched = null;
foreach ($domains as $d) {
    $dn = strtolower($d['name']);
    if ($hostname === $dn) { $matched = ['domain'=>$d, 'sub'=>'']; break; }
    if (str_ends_with($hostname, '.' . $dn)) {
        $sub = substr($hostname, 0, -1 - strlen($dn));
        $matched = ['domain'=>$d, 'sub'=>$sub];
        break;
    }
}
if (!$matched) resp('nohost');

// Fetch record row for this user + fqdn
$stmt = $pdo->prepare('SELECT r.* FROM records r WHERE r.user_id=? AND r.domain_id=? AND r.sub_name=? LIMIT 1');
$stmt->execute([(int)$u['id'], (int)$matched['domain']['id'], $matched['sub']]);
$rec = $stmt->fetch();
if (!$rec) resp('nohost');

$hz = new HetznerClient($env);
$namePart = ($rec['sub_name']==='') ? '@' : $rec['sub_name'];
$ttl = (int)$rec['ttl'];

$respText = [];

if ($myip4 && filter_var($myip4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    if (!empty($rec['hetzner_record_id_a'])) {
        if ($rec['last_ipv4'] !== $myip4) {
            $hz->updateRecordFull($rec['hetzner_record_id_a'], $matched['domain']['zone_id'], $namePart, 'A', $myip4, $ttl);
            $pdo->prepare('UPDATE records SET last_ipv4=?, updated_at=NOW() WHERE id=?')->execute([$myip4, $rec['id']]);
            $respText[] = "good {$myip4}";
        } else {
            $respText[] = "nochg {$myip4}";
        }
    } else {
        $r = $hz->createRecord($matched['domain']['zone_id'], $namePart, 'A', $myip4, $ttl);
        $pdo->prepare('UPDATE records SET hetzner_record_id_a=?, last_ipv4=?, updated_at=NOW() WHERE id=?')->execute([$r['id'] ?? null, $myip4, $rec['id']]);
        $respText[] = "good {$myip4}";
    }
}

if ($myip6 && filter_var($myip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    if (!empty($rec['hetzner_record_id_aaaa'])) {
        if ($rec['last_ipv6'] !== $myip6) {
            $hz->updateRecordFull($rec['hetzner_record_id_aaaa'], $matched['domain']['zone_id'], $namePart, 'AAAA', $myip6, $ttl);
            $pdo->prepare('UPDATE records SET last_ipv6=?, updated_at=NOW() WHERE id=?')->execute([$myip6, $rec['id']]);
            $respText[] = "good {$myip6}";
        } else {
            $respText[] = "nochg {$myip6}";
        }
    } else {
        $r = $hz->createRecord($matched['domain']['zone_id'], $namePart, 'AAAA', $myip6, $ttl);
        $pdo->prepare('UPDATE records SET hetzner_record_id_aaaa=?, last_ipv6=?, updated_at=NOW() WHERE id=?')->execute([$r['id'] ?? null, $myip6, $rec['id']]);
        $respText[] = "good {$myip6}";
    }
}

if (!$respText) {
    resp('nochg');
}

resp(implode("\n", $respText));
