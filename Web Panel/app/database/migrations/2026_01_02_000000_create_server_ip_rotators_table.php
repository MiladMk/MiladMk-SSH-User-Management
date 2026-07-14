<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('server_ip_rotators')) {
            Schema::create('server_ip_rotators', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->default('hetzner'); // future: more providers
                $table->string('hetzner_token')->nullable();
                $table->string('server_name')->nullable();
                $table->string('location')->nullable();          // hetzner location code (fsn1, nbg1, ...)
                $table->string('cf_email')->nullable();
                $table->string('cf_global_key')->nullable();
                $table->string('cf_zone_id')->nullable();
                $table->string('cf_record_id')->nullable();
                $table->string('domain_name')->nullable();
                $table->string('interface')->default('eth0');
                $table->text('used_ips')->nullable();            // newline separated blacklist
                $table->string('last_ip')->nullable();
                $table->text('last_log')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_ip_rotators');
    }
};
