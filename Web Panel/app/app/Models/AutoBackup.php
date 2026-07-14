<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoBackup extends Model
{
    protected $table = 'auto_backups';

    protected $fillable = [
        'api_token','bot_token','chat_id','backup_name','run_time','status','last_run_at','last_log',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
    ];
}
