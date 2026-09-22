<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // The permanent public identifier. This NEVER changes once printed.
            $table->string('code', 32)->unique();

            $table->foreignId('qr_batch_id')->nullable()->constrained('qr_batches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('label')->nullable();
            $table->text('target_url')->nullable();

            $table->boolean('is_active')->default(true);
            // When false the owner can see the QR but only a super admin may change its destination.
            $table->boolean('user_can_edit')->default(true);

            $table->unsignedBigInteger('scan_count')->default(0);
            $table->unsignedBigInteger('unique_scan_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('assigned_at')->nullable();

            // Per-code QR rendering options (colours, margin, size).
            $table->json('design')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index('qr_batch_id');
            $table->index('last_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
