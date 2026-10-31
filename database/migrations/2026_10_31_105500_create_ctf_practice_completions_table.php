<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ctf_practice_completions', function (Blueprint $table) {
            $table->uuid('challenge_id')->primary();
            $table->foreign('challenge_id')->references('challenge_id')->on('ctf_challenges')->cascadeOnDelete();
            $table->string('target_php_version', 16);
            $table->string('patched_php_version', 16);
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctf_practice_completions');
    }
};
