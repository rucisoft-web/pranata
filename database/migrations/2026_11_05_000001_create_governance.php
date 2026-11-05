<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('governance_assessments', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('year'); $t->string('status', 20)->default('open');
            $t->foreignId('coordinator_id')->constrained('users'); $t->foreignId('created_by')->constrained('users');
            $t->text('approval_note')->nullable(); $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable(); $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->unique(['entity_id', 'year']);
        });
        Schema::create('governance_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('governance_assessment_id')->constrained()->restrictOnDelete();
            $t->string('code', 10); $t->string('status', 20)->default('not_assessed');
            $t->foreignId('pic_id')->nullable()->constrained('users');
            $t->text('implementation')->nullable(); $t->text('evidence_note')->nullable();
            $t->text('explanation')->nullable(); $t->text('alternative_practice')->nullable(); $t->date('target_date')->nullable();
            $t->string('review_status', 20)->default('draft');
            $t->timestamp('submitted_at')->nullable(); $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->text('review_note')->nullable(); $t->foreignId('reviewed_by')->nullable()->constrained('users');
            $t->timestamp('reviewed_at')->nullable(); $t->unsignedInteger('revision')->default(1); $t->timestamps();
            $t->unique(['governance_assessment_id', 'code']); $t->index(['entity_id', 'pic_id']);
        });
        Schema::create('governance_actions', function (Blueprint $t) {
            $t->id(); $t->foreignId('entity_id')->constrained()->restrictOnDelete();
            $t->foreignId('governance_item_id')->constrained()->restrictOnDelete();
            $t->string('title', 255); $t->foreignId('pic_id')->constrained('users'); $t->date('due_date');
            $t->string('status', 20)->default('open'); $t->text('completion_note')->nullable();
            $t->timestamp('completed_at')->nullable(); $t->foreignId('created_by')->constrained('users'); $t->timestamps();
            $t->index(['entity_id', 'pic_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['governance_actions', 'governance_items', 'governance_assessments'] as $table) { Schema::dropIfExists($table); }
    }
};
