<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            // Denormalised owner so a user's analytics never needs to join qr_codes.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Raw IPs are never stored; this is a salted hash used only for unique counting.
            $table->string('ip_hash', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 80)->nullable();
            $table->string('city', 120)->nullable();

            $table->string('device_type', 20)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('browser', 60)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_unique')->default(false);

            $table->string('referrer_host')->nullable();
            $table->text('referrer_url')->nullable();
            $table->text('target_url')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('scanned_at')->index();
            $table->timestamps();

            $table->index(['qr_code_id', 'scanned_at']);
            $table->index(['user_id', 'scanned_at']);
            $table->index(['qr_code_id', 'ip_hash']);
            $table->index(['is_bot', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scans');
    }
};
