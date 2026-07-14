<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloudflareRotator extends Model
{
    use HasFactory;

    protected $table = 'cloudflare_rotators';

    protected $fillable = [
        'api_token',
        'zone_id',
        'record_name',
        'ip_list',
        'mode',
        'interval_minutes',
        'proxied',
        'status',
        'current_index',
        'last_ip',
        'last_rotated_at',
    ];

    protected $casts = [
        'proxied'          => 'boolean',
        'interval_minutes' => 'integer',
        'current_index'    => 'integer',
        'last_rotated_at'  => 'datetime',
    ];

    /**
     * Return the IP list as a clean array.
     */
    public function ips(): array
    {
        $raw = preg_split('/[\s,]+/', (string) $this->ip_list);
        $ips = array_values(array_filter(array_map('trim', $raw), function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP) !== false;
        }));
        return $ips;
    }
}
