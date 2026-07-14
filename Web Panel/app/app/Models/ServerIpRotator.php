<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerIpRotator extends Model
{
    protected $table = 'server_ip_rotators';

    protected $fillable = [
        'provider','hetzner_token','server_name','location',
        'cf_email','cf_global_key','cf_zone_id','cf_record_id',
        'domain_name','interface','used_ips','last_ip','last_log','last_run_at',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
    ];
}
