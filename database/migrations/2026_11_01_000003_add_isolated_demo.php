<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', fn (Blueprint $t) => $t->boolean('is_demo')->default(false)->index());
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_demo_guest')->default(false)->index());
    }
    public function down(): void
    {
        Schema::table('entities', fn (Blueprint $t) => $t->dropColumn('is_demo'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_demo_guest'));
    }
};
