<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cloudflare_rotators')) {
            Schema::create('cloudflare_rotators', function (Blueprint $table) {
                $table->id();
                $table->string('api_token');          // Cloudflare API token
                $table->string('zone_id');            // Cloudflare Zone ID
                $table->string('record_name');        // e.g. mc1.notahrim.com
                $table->text('ip_list');              // newline/comma separated IPs
                $table->string('mode')->default('round_robin'); // round_robin | random
                $table->integer('interval_minutes')->default(60);
                $table->boolean('proxied')->default(false);     // Cloudflare orange-cloud
                $table->string('status')->default('deactive');  // active | deactive
                $table->integer('current_index')->default(0);   // for round-robin
                $table->string('last_ip')->nullable();
                $table->timestamp('last_rotated_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_rotators');
    }
};
