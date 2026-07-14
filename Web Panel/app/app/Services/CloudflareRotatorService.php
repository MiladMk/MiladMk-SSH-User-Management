<?php

namespace App\Services;

use App\Models\CloudflareRotator;
use Illuminate\Support\Carbon;

/**
 * Self-contained Cloudflare DNS rotator.
 *
 * It updates the A record of a given hostname to the next IP from a list,
 * either round-robin or random. It talks directly to the Cloudflare API v4
 * from this server — there is no external/third-party dependency.
 */
class CloudflareRotatorService
{
    const API_BASE = 'https://api.cloudflare.com/client/v4';

    /**
     * Rotate a single config once. Returns an array describing the result.
     *
     * @param  bool  $force  When false, respects interval_minutes (used by cron).
     */
    public function rotate(CloudflareRotator $cfg, bool $force = false): array
    {
        if ($cfg->status !== 'active') {
            return ['ok' => false, 'message' => 'Rotator is not active'];
        }

        $ips = $cfg->ips();
        if (count($ips) === 0) {
            return ['ok' => false, 'message' => 'IP list is empty or invalid'];
        }

        // Respect the interval unless forced (manual "rotate now").
        if (!$force && $cfg->last_rotated_at) {
            $due = $cfg->last_rotated_at->copy()->addMinutes(max(1, (int) $cfg->interval_minutes));
            if (Carbon::now()->lt($due)) {
                return ['ok' => true, 'skipped' => true, 'message' => 'Not due yet'];
            }
        }

        // Choose next IP
        if ($cfg->mode === 'random') {
            $ip = $ips[array_rand($ips)];
            $nextIndex = $cfg->current_index; // random doesn't advance the pointer
        } else { // round_robin
            $idx = ((int) $cfg->current_index) % count($ips);
            $ip = $ips[$idx];
            $nextIndex = ($idx + 1) % count($ips);
        }

        // Find the DNS record id for record_name
        $recordId = $this->findRecordId($cfg, $cfg->record_name);
        if ($recordId === null) {
            // Try to create it if it doesn't exist
            $created = $this->createRecord($cfg, $ip);
            if (!$created['ok']) {
                return ['ok' => false, 'message' => 'Record not found and could not be created: ' . $created['message']];
            }
            $cfg->update([
                'current_index'   => $nextIndex,
                'last_ip'         => $ip,
                'last_rotated_at' => Carbon::now(),
            ]);
            return ['ok' => true, 'ip' => $ip, 'created' => true];
        }

        // Update the record
        $res = $this->apiRequest($cfg, 'PUT', "/zones/{$cfg->zone_id}/dns_records/{$recordId}", [
            'type'    => 'A',
            'name'    => $cfg->record_name,
            'content' => $ip,
            'ttl'     => 60,
            'proxied' => (bool) $cfg->proxied,
        ]);

        if (!$res['ok']) {
            return ['ok' => false, 'message' => $res['message']];
        }

        $cfg->update([
            'current_index'   => $nextIndex,
            'last_ip'         => $ip,
            'last_rotated_at' => Carbon::now(),
        ]);

        return ['ok' => true, 'ip' => $ip];
    }

    private function findRecordId(CloudflareRotator $cfg, string $name): ?string
    {
        $res = $this->apiRequest($cfg, 'GET', "/zones/{$cfg->zone_id}/dns_records?type=A&name=" . urlencode($name));
        if ($res['ok'] && !empty($res['data']['result'][0]['id'])) {
            return $res['data']['result'][0]['id'];
        }
        return null;
    }

    private function createRecord(CloudflareRotator $cfg, string $ip): array
    {
        return $this->apiRequest($cfg, 'POST', "/zones/{$cfg->zone_id}/dns_records", [
            'type'    => 'A',
            'name'    => $cfg->record_name,
            'content' => $ip,
            'ttl'     => 60,
            'proxied' => (bool) $cfg->proxied,
        ]);
    }

    /**
     * Minimal Cloudflare API caller using cURL (no extra Composer deps).
     */
    private function apiRequest(CloudflareRotator $cfg, string $method, string $path, array $body = null): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $cfg->api_token,
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'message' => 'cURL error: ' . $err];
        }

        $data = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && isset($data['success']) && $data['success'] === true) {
            return ['ok' => true, 'data' => $data];
        }

        $msg = 'HTTP ' . $code;
        if (isset($data['errors'][0]['message'])) {
            $msg .= ': ' . $data['errors'][0]['message'];
        }
        return ['ok' => false, 'message' => $msg, 'data' => $data];
    }
}
