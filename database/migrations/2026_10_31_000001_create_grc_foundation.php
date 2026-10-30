<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('entities')->restrictOnDelete();
            $table->boolean('is_bumn')->default(false);
            $table->boolean('is_public_company')->default(false);
            $table->boolean('is_listed')->default(false);
            $table->timestamps();
        });
        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['admin', 'risk_manager', 'risk_owner', 'viewer']);
            $table->timestamps();
            $table->unique(['entity_id', 'user_id']);
        });
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('title', 200);
            $table->string('category', 40);
            $table->string('objective', 500);
            $table->string('process', 200);
            $table->text('cause');
            $table->text('event');
            $table->text('impact_description');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('impact');
            $table->text('assessment_reason');
            $table->string('response', 20);
            $table->text('treatment_plan');
            $table->date('review_date');
            $table->string('methodology_version', 30)->default('PRN-INITIAL-1');
            $table->enum('status', ['draft', 'submitted', 'approved'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['entity_id', 'code']);
            $table->index(['entity_id', 'status']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
            $table->index(['entity_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('risks');
        Schema::dropIfExists('entity_user');
        Schema::dropIfExists('entities');
        Schema::dropIfExists('users');
    }
};
