<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('email');
            $table->string('company')->nullable()->after('role');
            $table->string('phone', 40)->nullable()->after('company');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->text('notes')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->after('last_login_at')
                ->constrained('users')->nullOnDelete();

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'company', 'phone', 'is_active', 'notes', 'last_login_at']);
        });
    }
};
