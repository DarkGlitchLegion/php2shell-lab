<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use SodiumException;

class VerifierAttestationService
{
    private const MAX_PAYLOAD_BYTES = 4096;

    private const MAX_ATTESTATION_AGE_SECONDS = 300;

    private const ALLOWED_CLOCK_SKEW_SECONDS = 30;

    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D';

    public function recordRateLimited(Request $request): void
    {
        $this->writeAudit($request, 'rate_limited', null, null, null);
    }

    /**
     * Accept and persist a signed verifier attestation.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function accept(Request $request): array
    {
        if ($request->getContentTypeFormat() !== 'json') {
            return $this->reject($request, 'content_type_must_be_json', 415, null, null, null);
        }

        $contentLength = $request->headers->get('Content-Length');
        if (is_string($contentLength) && preg_match('/\A\d+\z/D', $contentLength) === 1 && (float) $contentLength > self::MAX_PAYLOAD_BYTES) {
            return $this->reject($request, 'payload_too_large', 413, null, null, null);
        }

        $body = $this->readBoundedBody($request);

        if ($body === null) {
            return $this->reject($request, 'payload_too_large', 413, null, null, null);
        }

        $payloadHash = hash('sha256', $body);
        $payload = $this->decodeCanonicalPayload($body);

        if ($payload === null) {
            return $this->reject($request, 'malformed_payload', 400, null, null, $payloadHash);
        }

        $signature = $this->decodeSignature($request->header('X-CTF-Signature'));
        $publicKey = $this->decodePublicKey(config('lab.ctf.verifier_public_key'));

        if ($publicKey === null) {
            return $this->reject($request, 'verifier_not_configured', 503, $payload['event_id'], $payload['challenge_id'], $payloadHash);
        }

        if ($signature === null) {
            return $this->reject($request, 'missing_or_malformed_signature', 401, $payload['event_id'], $payload['challenge_id'], $payloadHash);
        }

        try {
            $signatureIsValid = sodium_crypto_sign_verify_detached(
                $signature,
                $this->canonicalPayload($payload),
                $publicKey,
            );
        } catch (SodiumException) {
            $signatureIsValid = false;
        }

        if (! $signatureIsValid) {
            return $this->reject($request, 'invalid_signature', 401, $payload['event_id'], $payload['challenge_id'], $payloadHash);
        }

        $validationFailure = $this->validationFailure($payload);

        if ($validationFailure !== null) {
            return $this->reject(
                $request,
                $validationFailure['reason'],
                $validationFailure['status'],
                $payload['event_id'],
                $payload['challenge_id'],
                $payloadHash,
            );
        }

        try {
            $result = DB::transaction(function () use ($request, $payload, $payloadHash): array {
                if (DB::table('ctf_verifier_events')->where('event_id', $payload['event_id'])->exists()) {
                    $this->writeAudit($request, 'duplicate_event', $payload['event_id'], $payload['challenge_id'], $payloadHash);

                    return ['status' => 409, 'body' => ['accepted' => false, 'reason' => 'duplicate_event']];
                }

                $challenge = DB::table('ctf_challenges')
                    ->where('challenge_id', $payload['challenge_id'])
                    ->lockForUpdate()
                    ->first();

                if ($challenge === null) {
                    $this->writeAudit($request, 'unknown_challenge', $payload['event_id'], $payload['challenge_id'], $payloadHash);

                    return ['status' => 404, 'body' => ['accepted' => false, 'reason' => 'unknown_challenge']];
                }

                if ($challenge->status !== 'active') {
                    $this->writeAudit($request, 'challenge_already_completed', $payload['event_id'], $payload['challenge_id'], $payloadHash);

                    return ['status' => 409, 'body' => ['accepted' => false, 'reason' => 'challenge_already_completed']];
                }

                $updated = DB::table('ctf_challenges')
                    ->where('challenge_id', $payload['challenge_id'])
                    ->where('status', 'active')
                    ->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);

                if ($updated !== 1) {
                    $this->writeAudit($request, 'challenge_state_conflict', $payload['event_id'], $payload['challenge_id'], $payloadHash);

                    return ['status' => 409, 'body' => ['accepted' => false, 'reason' => 'challenge_state_conflict']];
                }

                DB::table('ctf_verifier_events')->insert([
                    'event_id' => $payload['event_id'],
                    'challenge_id' => $payload['challenge_id'],
                    'target_id' => $payload['target_id'],
                    'target_php_version' => $payload['target_php_version'],
                    'verification_status' => $payload['verification_status'],
                    'issued_at' => $this->parseTimestamp($payload['issued_at'])->format('Y-m-d H:i:s'),
                    'expires_at' => $this->parseTimestamp($payload['expires_at'])->format('Y-m-d H:i:s'),
                    'evidence_reference' => $payload['evidence_reference'],
                    'payload_sha256' => $payloadHash,
                    'created_at' => now(),
                ]);

                $this->writeAudit($request, 'accepted', $payload['event_id'], $payload['challenge_id'], $payloadHash);

                return ['status' => 202, 'body' => ['accepted' => true, 'event_id' => $payload['event_id']]];
            });

            return $result;
        } catch (QueryException $exception) {
            if (DB::table('ctf_verifier_events')->where('event_id', $payload['event_id'])->exists()) {
                return $this->reject($request, 'duplicate_event', 409, $payload['event_id'], $payload['challenge_id'], $payloadHash);
            }

            Log::error('ctf_attestation_persistence_failed', [
                'event_id' => $payload['event_id'],
                'challenge_id' => $payload['challenge_id'],
                'exception' => $exception::class,
            ]);

            return $this->reject($request, 'persistence_failure', 503, $payload['event_id'], $payload['challenge_id'], $payloadHash);
        }
    }

    /**
     * Decode a canonical JSON object with exactly the contract's string fields.
     *
     * @return array<string,string>|null
     */
    private function decodeCanonicalPayload(string $body): ?array
    {
        if ($body === '' || strlen($body) > self::MAX_PAYLOAD_BYTES) {
            return null;
        }

        try {
            $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload) || array_is_list($payload)) {
            return null;
        }

        $expectedKeys = [
            'challenge_id',
            'event_id',
            'evidence_reference',
            'expires_at',
            'issued_at',
            'target_id',
            'target_php_version',
            'verification_status',
        ];

        if (array_keys($payload) !== $expectedKeys) {
            return null;
        }

        foreach ($payload as $value) {
            if (! is_string($value) || preg_match('/[^\x20-\x7E]/', $value) === 1) {
                return null;
            }
        }

        try {
            return hash_equals($this->canonicalPayload($payload), $body) ? $payload : null;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Serialize the fixed ASCII-only payload in RFC 8785 property order.
     *
     * @param  array<string,string>  $payload
     *
     * @throws JsonException
     */
    private function canonicalPayload(array $payload): string
    {
        $ordered = [];
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            $ordered[$key] = $payload[$key];
        }

        return json_encode($ordered, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{reason:string,status:int}|null
     */
    private function validationFailure(array $payload): ?array
    {
        if (preg_match(self::UUID_PATTERN, $payload['event_id']) !== 1 || preg_match(self::UUID_PATTERN, $payload['challenge_id']) !== 1) {
            return ['reason' => 'invalid_identifier', 'status' => 422];
        }

        if ($payload['target_id'] !== 'php2shell-vulnerable' || $payload['target_php_version'] !== '8.5.11') {
            return ['reason' => 'incorrect_target', 'status' => 422];
        }

        if ($payload['verification_status'] !== 'verified') {
            return ['reason' => 'verification_not_confirmed', 'status' => 422];
        }

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $payload['evidence_reference']) !== 1) {
            return ['reason' => 'invalid_evidence_reference', 'status' => 422];
        }

        try {
            $issuedAt = $this->parseTimestamp($payload['issued_at']);
            $expiresAt = $this->parseTimestamp($payload['expires_at']);
        } catch (\ValueError) {
            return ['reason' => 'invalid_timestamp', 'status' => 422];
        }

        $now = now()->toImmutable();

        if ($issuedAt->getTimestamp() > $now->getTimestamp() + self::ALLOWED_CLOCK_SKEW_SECONDS
            || $issuedAt->getTimestamp() < $now->getTimestamp() - self::MAX_ATTESTATION_AGE_SECONDS
            || $expiresAt <= $now
            || $expiresAt <= $issuedAt
            || $expiresAt->getTimestamp() - $issuedAt->getTimestamp() > self::MAX_ATTESTATION_AGE_SECONDS) {
            return ['reason' => 'attestation_expired_or_outside_window', 'status' => 422];
        }

        return null;
    }

    private function parseTimestamp(string $value): DateTimeImmutable
    {
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));

        if (! $timestamp || $timestamp->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new \ValueError('Invalid UTC timestamp.');
        }

        return $timestamp;
    }

    private function decodeSignature(?string $encoded): ?string
    {
        if ($encoded === null || preg_match('/\A[A-Za-z0-9_-]{86}\z/D', $encoded) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($encoded.'==', '-_', '+/'), true);

        return is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_BYTES ? $decoded : null;
    }

    private function readBoundedBody(Request $request): ?string
    {
        $content = $request->getContent(false);
        $body = is_resource($content)
            ? stream_get_contents($content, self::MAX_PAYLOAD_BYTES + 1)
            : $content;

        if (! is_string($body) || strlen($body) > self::MAX_PAYLOAD_BYTES) {
            return null;
        }

        return $body;
    }

    private function decodePublicKey(mixed $encoded): ?string
    {
        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        return is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $decoded : null;
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function reject(Request $request, string $reason, int $status, ?string $eventId, ?string $challengeId, ?string $payloadHash): array
    {
        $this->writeAudit($request, $reason, $eventId, $challengeId, $payloadHash);

        return ['status' => $status, 'body' => ['accepted' => false, 'reason' => $reason]];
    }

    private function writeAudit(Request $request, string $outcome, ?string $eventId, ?string $challengeId, ?string $payloadHash): void
    {
        $eventId = is_string($eventId) && preg_match(self::UUID_PATTERN, $eventId) === 1 ? $eventId : null;
        $challengeId = is_string($challengeId) && preg_match(self::UUID_PATTERN, $challengeId) === 1 ? $challengeId : null;

        DB::table('ctf_verification_audits')->insert([
            'event_id' => $eventId,
            'challenge_id' => $challengeId,
            'payload_sha256' => $payloadHash,
            'outcome' => $outcome,
            'source_ip' => substr((string) $request->ip(), 0, 45),
            'created_at' => now(),
        ]);

        Log::notice('ctf_verifier_attestation', [
            'event_id' => $eventId,
            'challenge_id' => $challengeId,
            'outcome' => $outcome,
            'payload_sha256' => $payloadHash,
        ]);
    }
}
