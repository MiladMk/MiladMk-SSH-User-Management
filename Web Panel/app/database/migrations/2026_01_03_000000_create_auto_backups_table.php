<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auto_backups')) {
            Schema::create('auto_backups', function (Blueprint $table) {
                $table->id();
                $table->string('api_token')->nullable();
                $table->string('bot_token')->nullable();
                $table->string('chat_id')->nullable();
                $table->string('backup_name')->default('backup');
                $table->string('run_time')->default('02:00'); // HH:MM 24h
                $table->string('status')->default('deactive'); // active | deactive
                $table->timestamp('last_run_at')->nullable();
                $table->text('last_log')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_backups');
    }
};
