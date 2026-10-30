<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regulatory_sources', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->string('code', 40); $t->string('title', 255); $t->string('issuer', 255);
            $t->string('reference', 255); $t->string('version', 80); $t->string('url', 1000)->nullable();
            $t->date('effective_date'); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
            $t->unique(['entity_id', 'code']);
        });
        Schema::create('obligations', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('regulatory_source_id')->constrained()->restrictOnDelete();
            $t->string('code', 40); $t->string('title', 255); $t->text('description'); $t->string('clause', 255);
            $t->string('frequency', 20); $t->string('criticality', 20);
            $t->string('applicability', 20); $t->text('applicability_reason');
            $t->foreignId('responsible_id')->constrained('users'); $t->foreignId('created_by')->constrained('users');
            $t->unsignedInteger('revision')->default(1); $t->timestamps(); $t->unique(['entity_id', 'code']);
        });
        Schema::create('obligation_occurrences', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('obligation_id')->constrained()->restrictOnDelete(); $t->json('obligation_snapshot');
            $t->string('period', 80); $t->date('due_date'); $t->foreignId('responsible_id')->constrained('users');
            $t->string('status', 20)->default('open'); $t->text('submission_note')->nullable();
            $t->timestamp('submitted_at')->nullable(); $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->text('review_note')->nullable(); $t->timestamp('fulfilled_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->unique(['obligation_id', 'period']); $t->index(['entity_id', 'status', 'due_date']);
        });
        Schema::create('audit_engagements', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->string('code', 40); $t->string('title', 255); $t->text('scope'); $t->text('objectives');
            $t->date('start_date'); $t->date('end_date');
            $t->foreignId('lead_id')->constrained('users'); $t->foreignId('reviewer_id')->constrained('users');
            $t->foreignId('created_by')->constrained('users'); $t->string('status', 20)->default('draft');
            $t->text('report_summary')->nullable(); $t->text('review_note')->nullable();
            $t->timestamp('finalized_at')->nullable(); $t->timestamp('closed_at')->nullable();
            $t->unsignedInteger('revision')->default(1); $t->timestamps(); $t->unique(['entity_id', 'code']);
        });
        Schema::create('audit_procedures', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('audit_engagement_id')->constrained()->restrictOnDelete();
            $t->string('title', 255); $t->text('criteria'); $t->text('procedure'); $t->text('result');
            $t->string('conclusion', 30); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
        });
        Schema::create('audit_findings', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('audit_engagement_id')->constrained()->restrictOnDelete();
            $t->string('title', 255); $t->text('criteria'); $t->text('condition'); $t->text('cause');
            $t->text('impact'); $t->text('recommendation'); $t->string('severity', 20);
            $t->foreignId('responsible_id')->constrained('users'); $t->date('due_date');
            $t->string('status', 20)->default('draft'); $t->text('action_plan')->nullable();
            $t->text('completion_note')->nullable(); $t->timestamp('submitted_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->text('review_note')->nullable(); $t->foreignId('closed_by')->nullable()->constrained('users');
            $t->timestamp('closed_at')->nullable(); $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->index(['entity_id', 'responsible_id', 'status']);
        });
        Schema::create('evidence_files', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->string('subject_type', 50); $t->unsignedBigInteger('subject_id');
            $t->string('path'); $t->string('original_name'); $t->string('mime', 100);
            $t->unsignedBigInteger('size'); $t->string('sha256', 64); $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamps(); $t->index(['entity_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['evidence_files', 'audit_findings', 'audit_procedures', 'audit_engagements', 'obligation_occurrences', 'obligations', 'regulatory_sources'] as $table) { Schema::dropIfExists($table); }
    }
};
