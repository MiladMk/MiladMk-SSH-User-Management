<?php

namespace App\Services;

use App\Models\ServerIpRotator;
use Illuminate\Support\Carbon;

/**
 * Hetzner floating-IP rotator (self-contained, PHP port of the reference script).
 *
 * Flow per run:
 *   1. Load blacklist of used IPs.
 *   2. Create fresh Hetzner floating IPs until one is not in the blacklist.
 *   3. Assign it to the target server.
 *   4. Add it to the server's network interface.
 *   5. Update the Cloudflare A record.
 *   6. Clean up unused floating IPs that belong to this project.
 *
 * All steps append to a log string returned to the caller (shown in the UI).
 */
class HetznerRotatorService
{
    const HETZNER_API = 'https://api.hetzner.cloud/v1';
    const PROJECT_LABEL_KEY = 'project';
    const PROJECT_LABEL_VAL = 'miladmk-auto-rotate';

    private $log = [];

    private function log($msg)
    {
        $line = '[' . date('H:i:s') . '] ' . $msg;
        $this->log[] = $line;
    }

    public function getLog(): string
    {
        return implode("\n", $this->log);
    }

    /** Hetzner API helper. */
    private function hetzner($token, $method, $path, $body = null)
    {
        $ch = curl_init(self::HETZNER_API . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['ok' => false, 'code' => 0, 'error' => $err, 'data' => null];
        }
        return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'data' => json_decode($raw, true)];
    }

    /** Update Cloudflare A record using Global API Key. */
    private function updateCloudflare(ServerIpRotator $cfg, $newIp): bool
    {
        $url = "https://api.cloudflare.com/client/v4/zones/{$cfg->cf_zone_id}/dns_records/{$cfg->cf_record_id}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Auth-Email: ' . $cfg->cf_email,
            'X-Auth-Key: ' . $cfg->cf_global_key,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'type' => 'A', 'name' => $cfg->domain_name, 'content' => $newIp,
            'ttl' => 60, 'proxied' => false,
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($raw, true);
        return isset($data['success']) && $data['success'] === true;
    }

    /** Main entry: perform one rotation. */
    public function rotate(ServerIpRotator $cfg): array
    {
        $this->log = [];
        $token = $cfg->hetzner_token;

        if (empty($token) || empty($cfg->server_name) || empty($cfg->location)) {
            $this->log('ERROR: Hetzner token, server name and location are required.');
            return ['ok' => false, 'log' => $this->getLog()];
        }

        // 1. blacklist
        $used = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $cfg->used_ips)));
        $used = array_values(array_unique($used));
        $this->log('Loaded ' . count($used) . ' blacklisted IP(s).');

        // find server id by name
        $srv = $this->hetzner($token, 'GET', '/servers?name=' . urlencode($cfg->server_name));
        if (!$srv['ok'] || empty($srv['data']['servers'][0]['id'])) {
            $this->log("ERROR: Server '{$cfg->server_name}' not found.");
            return ['ok' => false, 'log' => $this->getLog()];
        }
        $serverId = $srv['data']['servers'][0]['id'];
        $this->log("Server found (id={$serverId}).");

        // 2. create fresh floating IPs until one is not blacklisted
        $this->log('Searching for a fresh IP...');
        $newFip = null;
        $temporary = [];
        $attempts = 0;
        while ($attempts < 15) {
            $attempts++;
            $create = $this->hetzner($token, 'POST', '/floating_ips', [
                'type' => 'ipv4',
                'home_location' => $cfg->location,
                'labels' => [self::PROJECT_LABEL_KEY => self::PROJECT_LABEL_VAL],
            ]);
            if (!$create['ok'] || empty($create['data']['floating_ip'])) {
                $msg = $create['data']['error']['message'] ?? ('HTTP ' . $create['code']);
                $this->log('ERROR creating floating IP: ' . $msg);
                break;
            }
            $fip = $create['data']['floating_ip'];
            $ip  = $fip['ip'];
            if (!in_array($ip, $used, true)) {
                $newFip = $fip;
                $this->log("Success! New IP: {$ip}");
                break;
            }
            $this->log("IP {$ip} is blacklisted. Holding to force a new one...");
            $temporary[] = $fip['id'];
        }

        if (!$newFip) {
            $this->log('ERROR: could not obtain a fresh IP. Cleaning temporary IPs...');
            foreach ($temporary as $tid) { $this->hetzner($token, 'DELETE', "/floating_ips/{$tid}"); }
            return ['ok' => false, 'log' => $this->getLog()];
        }

        // 3. assign to server
        $this->log("Assigning {$newFip['ip']} to server...");
        $assign = $this->hetzner($token, 'POST', "/floating_ips/{$newFip['id']}/actions/assign", ['server' => $serverId]);
        if (!$assign['ok']) {
            $this->log('ERROR: assign failed (HTTP ' . $assign['code'] . ').');
            return ['ok' => false, 'log' => $this->getLog()];
        }
        sleep(2);

        // 4. add to interface locally
        $iface = $cfg->interface ?: 'eth0';
        @exec("sudo ip addr add " . escapeshellarg($newFip['ip'] . '/32') . " dev " . escapeshellarg($iface) . " 2>/dev/null");
        $this->log("Added {$newFip['ip']} to interface {$iface}.");

        // 5. Cloudflare
        if ($this->updateCloudflare($cfg, $newFip['ip'])) {
            $this->log('Cloudflare record updated successfully.');
            $used[] = $newFip['ip'];
        } else {
            $this->log('ERROR: Cloudflare update FAILED. Skipping cleanup for safety.');
            $cfg->update([
                'last_ip' => $newFip['ip'],
                'last_log' => $this->getLog(),
                'last_run_at' => Carbon::now(),
                'used_ips' => implode("\n", $used),
            ]);
            return ['ok' => false, 'log' => $this->getLog()];
        }

        // 6. cleanup other project floating IPs not attached elsewhere
        $this->log('Cleaning up unused project IPs...');
        $all = $this->hetzner($token, 'GET', '/floating_ips');
        if ($all['ok'] && !empty($all['data']['floating_ips'])) {
            foreach ($all['data']['floating_ips'] as $fip) {
                if ($fip['id'] == $newFip['id']) continue;
                $label = $fip['labels'][self::PROJECT_LABEL_KEY] ?? null;
                if ($label !== self::PROJECT_LABEL_VAL) continue;
                $attachedServer = $fip['server'] ?? null;
                if ($attachedServer === null || $attachedServer == $serverId) {
                    if ($attachedServer !== null) {
                        $this->hetzner($token, 'POST', "/floating_ips/{$fip['id']}/actions/unassign");
                    }
                    $this->hetzner($token, 'DELETE', "/floating_ips/{$fip['id']}");
                    $this->log("Cleaned up {$fip['ip']}.");
                } else {
                    $this->log("Skipping {$fip['ip']} - active on another server.");
                }
            }
        }

        $cfg->update([
            'last_ip' => $newFip['ip'],
            'last_log' => $this->getLog(),
            'last_run_at' => Carbon::now(),
            'used_ips' => implode("\n", array_values(array_unique($used))),
        ]);

        $this->log('Process completed successfully!');
        // persist final log
        $cfg->update(['last_log' => $this->getLog()]);

        return ['ok' => true, 'ip' => $newFip['ip'], 'log' => $this->getLog()];
    }
}
