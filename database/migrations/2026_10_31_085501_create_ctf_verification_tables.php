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
        Schema::create('ctf_challenges', function (Blueprint $table) {
            $table->uuid('challenge_id')->primary();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ctf_verifier_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->uuid('challenge_id')->unique();
            $table->foreign('challenge_id')->references('challenge_id')->on('ctf_challenges')->cascadeOnDelete();
            $table->string('target_id', 64);
            $table->string('target_php_version', 16);
            $table->string('verification_status', 16);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->string('evidence_reference', 128);
            $table->char('payload_sha256', 64);
            $table->timestamp('created_at');
        });

        Schema::create('ctf_verification_audits', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->nullable()->index();
            $table->uuid('challenge_id')->nullable()->index();
            $table->char('payload_sha256', 64)->nullable();
            $table->string('outcome', 48);
            $table->string('source_ip', 45)->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctf_verification_audits');
        Schema::dropIfExists('ctf_verifier_events');
        Schema::dropIfExists('ctf_challenges');
    }
};
