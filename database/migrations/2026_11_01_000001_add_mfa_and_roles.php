<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->json('mfa_recovery_hashes')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedBigInteger('mfa_last_counter')->nullable();
            $table->string('mfa_epoch', 36)->nullable();
        });
        Schema::table('entity_user', fn (Blueprint $table) => $table->enum('role', [
            'admin', 'risk_manager', 'risk_owner', 'viewer', 'compliance_officer', 'compliance_owner', 'audit_manager', 'auditor',
        ])->change());
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['mfa_secret', 'mfa_recovery_hashes', 'mfa_confirmed_at', 'mfa_last_counter', 'mfa_epoch']));
        // Expanded role values intentionally remain: do not silently replace assigned roles.
    }
};
