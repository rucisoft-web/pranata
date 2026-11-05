<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Peran responden untuk anggota Direksi dan Dewan Komisaris.
        Schema::table('entity_user', fn (Blueprint $table) => $table->enum('role', [
            'admin', 'risk_manager', 'risk_owner', 'viewer', 'compliance_officer', 'compliance_owner', 'audit_manager', 'auditor', 'board_member',
        ])->change());
        Schema::create('questionnaire_campaigns', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->string('template', 40); $t->string('title', 255); $t->unsignedSmallInteger('year');
            $t->date('due_date'); $t->string('status', 20)->default('open'); $t->boolean('anonymous');
            $t->text('instructions')->nullable(); $t->foreignId('created_by')->constrained('users');
            $t->foreignId('closed_by')->nullable()->constrained('users'); $t->timestamp('closed_at')->nullable(); $t->timestamps();
            $t->index(['entity_id', 'template', 'year'], 'q_campaigns_entity_template_year');
        });
        Schema::create('questionnaire_assignments', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('questionnaire_campaign_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained(); $t->string('status', 20)->default('pending');
            $t->timestamp('submitted_at')->nullable(); $t->timestamps();
            $t->unique(['questionnaire_campaign_id', 'user_id'], 'q_assignments_campaign_user_unique'); $t->index(['entity_id', 'user_id', 'status'], 'q_assignments_entity_user_status');
        });
        // Tanggapan anonim tidak menyimpan penugasan maupun waktu, dan memakai UUID agar urutan tidak menunjuk responden.
        Schema::create('questionnaire_responses', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('questionnaire_campaign_id')->constrained()->restrictOnDelete();
            $t->foreignId('questionnaire_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('answers'); $t->unsignedSmallInteger('exceptions')->default(0);
        });
    }

    public function down(): void
    {
        foreach (['questionnaire_responses', 'questionnaire_assignments', 'questionnaire_campaigns'] as $table) { Schema::dropIfExists($table); }
    }
};
