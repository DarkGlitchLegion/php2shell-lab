<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CtfAttestationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENDPOINT = '/internal/ctf/attestations';

    private string $verifierSecretKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        $this->verifierSecretKey = sodium_crypto_sign_secretkey($keyPair);

        config([
            'lab.ctf.enabled' => true,
            'lab.ctf.flag' => 'FLAG{signed_attestation_only}',
            'lab.ctf.verifier_public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ]);
    }

    public function test_valid_signed_attestation_completes_once_and_reveals_flag_only_to_the_challenge_session(): void
    {
        $this->freezeTime();
        $challengeId = $this->startChallenge();

        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonMissingPath('flag');

        $payload = $this->attestation($challengeId);
        $response = $this->postAttestation($payload);

        $response->assertStatus(202)
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('event_id', $payload['event_id'])
            ->assertJsonMissingPath('flag');

        $this->assertDatabaseHas('ctf_verifier_events', [
            'event_id' => $payload['event_id'],
            'challenge_id' => $challengeId,
            'target_id' => 'php2shell-vulnerable',
            'target_php_version' => '8.5.11',
            'verification_status' => 'verified',
            'evidence_reference' => 'evidence.local.001',
        ]);
        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'accepted',
        ]);

        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('flag', 'FLAG{signed_attestation_only}')
            ->assertJsonPath('verification.target_id', 'php2shell-vulnerable')
            ->assertJsonPath('verification.target_php_version', '8.5.11')
            ->assertJsonPath('verification.verification_status', 'verified')
            ->assertJsonPath('verification.evidence_reference', 'evidence.local.001');
    }

    public function test_missing_signature_is_rejected_and_audited_without_revealing_flag(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId);

        $this->send($this->canonical($payload), null)
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'missing_or_malformed_signature');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'missing_or_malformed_signature',
        ]);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_invalid_signature_is_rejected_and_audited(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId);

        $this->send($this->canonical($payload), str_repeat('A', 86))
            ->assertUnauthorized()
            ->assertJsonPath('reason', 'invalid_signature');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'invalid_signature',
        ]);
    }

    public function test_expired_signed_attestation_is_rejected_and_audited(): void
    {
        $this->freezeTime();
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId, [
            'issued_at' => now()->subMinutes(6)->format('Y-m-d\\TH:i:s\\Z'),
            'expires_at' => now()->subMinutes(1)->format('Y-m-d\\TH:i:s\\Z'),
        ]);

        $this->postAttestation($payload)
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'attestation_expired_or_outside_window');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'attestation_expired_or_outside_window',
        ]);
    }

    public function test_incorrect_target_or_php_version_is_rejected(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId, ['target_php_version' => '8.5.12']);

        $this->postAttestation($payload)
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'incorrect_target');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'incorrect_target',
        ]);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_duplicate_event_is_rejected_without_a_second_award(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId);

        $this->postAttestation($payload)->assertStatus(202);
        $this->postAttestation($payload)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'duplicate_event');

        $this->assertDatabaseCount('ctf_verifier_events', 1);
        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'duplicate_event',
        ]);
    }

    public function test_second_valid_event_cannot_award_the_same_challenge_twice(): void
    {
        $challengeId = $this->startChallenge();

        $this->postAttestation($this->attestation($challengeId))->assertStatus(202);
        $secondPayload = $this->attestation($challengeId);

        $this->postAttestation($secondPayload)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'challenge_already_completed');

        $this->assertDatabaseCount('ctf_verifier_events', 1);
        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $secondPayload['event_id'],
            'outcome' => 'challenge_already_completed',
        ]);
    }

    public function test_database_conflict_rolls_back_challenge_completion_and_does_not_award_flag(): void
    {
        $challengeId = $this->startChallenge();
        $existingEventId = (string) Str::uuid();
        DB::table('ctf_verifier_events')->insert([
            'event_id' => $existingEventId,
            'challenge_id' => $challengeId,
            'target_id' => 'php2shell-vulnerable',
            'target_php_version' => '8.5.11',
            'verification_status' => 'verified',
            'issued_at' => now(),
            'expires_at' => now()->addMinutes(2),
            'evidence_reference' => 'existing.evidence',
            'payload_sha256' => str_repeat('a', 64),
            'created_at' => now(),
        ]);
        $payload = $this->attestation($challengeId);

        $this->postAttestation($payload)
            ->assertServiceUnavailable()
            ->assertJsonPath('reason', 'persistence_failure');

        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'active']);
        $this->assertDatabaseCount('ctf_verifier_events', 1);
        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'persistence_failure',
        ]);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_non_verified_status_is_audited_but_does_not_award_flag(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId, ['verification_status' => 'not_verified']);

        $this->postAttestation($payload)
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'verification_not_confirmed');

        $this->assertDatabaseMissing('ctf_verifier_events', ['event_id' => $payload['event_id']]);
        $this->getJson(route('ctf.status'))
            ->assertJsonPath('status', 'active')
            ->assertJsonMissingPath('flag');
    }

    public function test_noncanonical_json_and_malformed_json_are_rejected_and_audited(): void
    {
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId);
        $canonical = $this->canonical($payload);

        $this->send(' '.$canonical, $this->signature($canonical))
            ->assertBadRequest()
            ->assertJsonPath('reason', 'malformed_payload');
        $this->send('{invalid', null)
            ->assertBadRequest()
            ->assertJsonPath('reason', 'malformed_payload');

        $this->assertDatabaseHas('ctf_verification_audits', ['outcome' => 'malformed_payload']);
        $this->assertDatabaseMissing('ctf_verifier_events', ['event_id' => $payload['event_id']]);
    }

    public function test_oversized_payload_is_rejected_without_storing_a_body_digest(): void
    {
        $this->send(str_repeat('x', 4097), null)
            ->assertStatus(413)
            ->assertJsonPath('reason', 'payload_too_large');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'outcome' => 'payload_too_large',
            'payload_sha256' => null,
        ]);
    }

    public function test_rate_limited_attestation_attempt_is_audited(): void
    {
        $body = $this->canonical($this->attestation((string) Str::uuid()));

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->send($body, null)->assertUnauthorized();
        }

        $this->send($body, null)
            ->assertStatus(429)
            ->assertJsonPath('reason', 'rate_limited');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'outcome' => 'rate_limited',
            'event_id' => null,
            'payload_sha256' => null,
        ]);
    }

    public function test_unknown_challenge_is_rejected_and_logged(): void
    {
        $payload = $this->attestation((string) Str::uuid());

        $this->postAttestation($payload)
            ->assertNotFound()
            ->assertJsonPath('reason', 'unknown_challenge');

        $this->assertDatabaseHas('ctf_verification_audits', [
            'event_id' => $payload['event_id'],
            'outcome' => 'unknown_challenge',
        ]);
    }

    public function test_verifier_key_must_be_configured_before_attestations_can_be_accepted(): void
    {
        config(['lab.ctf.verifier_public_key' => null]);
        $challengeId = $this->startChallenge();
        $payload = $this->attestation($challengeId);

        $this->postAttestation($payload)
            ->assertServiceUnavailable()
            ->assertJsonPath('reason', 'verifier_not_configured');

        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_completed_flag_is_not_available_to_a_session_without_the_challenge_id(): void
    {
        $challengeId = $this->startChallenge();
        $this->postAttestation($this->attestation($challengeId))->assertStatus(202);

        $this->flushSession();

        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonMissingPath('flag');
    }

    private function startChallenge(): string
    {
        $challengeId = (string) $this->postJson(route('ctf.start'))->assertOk()->json('challenge_id');
        $this->withSession(['ctf_challenge_id' => $challengeId]);

        return $challengeId;
    }

    /**
     * @param  array<string,string>  $overrides
     * @return array<string,string>
     */
    private function attestation(string $challengeId, array $overrides = []): array
    {
        return array_merge([
            'challenge_id' => $challengeId,
            'event_id' => (string) Str::uuid(),
            'evidence_reference' => 'evidence.local.001',
            'expires_at' => now()->addMinutes(4)->format('Y-m-d\\TH:i:s\\Z'),
            'issued_at' => now()->format('Y-m-d\\TH:i:s\\Z'),
            'target_id' => 'php2shell-vulnerable',
            'target_php_version' => '8.5.11',
            'verification_status' => 'verified',
        ], $overrides);
    }

    /** @param array<string,string> $payload */
    private function postAttestation(array $payload): TestResponse
    {
        $body = $this->canonical($payload);

        return $this->send($body, $this->signature($body));
    }

    /** @param array<string,string> $payload */
    private function canonical(array $payload): string
    {
        ksort($payload, SORT_STRING);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function signature(string $body): string
    {
        $signature = sodium_crypto_sign_detached($body, $this->verifierSecretKey);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function send(string $body, ?string $signature): TestResponse
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($signature !== null) {
            $server['HTTP_X_CTF_SIGNATURE'] = $signature;
        }

        return $this->call('POST', self::ENDPOINT, [], [], [], $server, $body);
    }
}
