<?php
class HetznerClient {
    private string $base;
    private string $token;

    public function __construct(array $env) {
        $this->base = rtrim($env['HETZNER_DNS_API'] ?? 'https://dns.hetzner.com/api/v1', '/');
        $this->token = $env['HETZNER_DNS_TOKEN'] ?? '';
    }

    private function req(string $method, string $path, array $query = [], array $body = null): array {
        $url = $this->base . $path;
        if ($query) $url .= '?' . http_build_query($query);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = [ 'Auth-API-Token: ' . $this->token ];
        if (!is_null($body)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        if ($resp === false) throw new RuntimeException('cURL error: ' . curl_error($ch));
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        if ($code >= 300) throw new RuntimeException('Hetzner API ' . $code . ': ' . $resp);
        return $data ?? [];
    }

    public function createRecord(string $zoneId, string $name, string $type, string $value, int $ttl = 300): array {
        $payload = ['zone_id'=>$zoneId,'name'=>$name,'type'=>$type,'value'=>$value,'ttl'=>$ttl];
        $res = $this->req('POST', '/records', [], $payload);
        return $res['record'] ?? [];
    }

    public function updateRecordFull(string $recordId, string $zoneId, string $name, string $type, string $value, int $ttl): array {
        $payload = ['zone_id'=>$zoneId,'name'=>$name,'type'=>$type,'value'=>$value,'ttl'=>$ttl];
        $res = $this->req('PUT', '/records/' . rawurlencode($recordId), [], $payload);
        return $res['record'] ?? [];
    }

    public function deleteRecord(string $recordId): void {
        $this->req('DELETE', '/records/' . rawurlencode($recordId));
    }
}
