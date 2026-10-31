<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CtfGameTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lab.ctf.enabled' => true,
            'lab.ctf.flag' => 'FLAG{test_php2shell}',
            'lab.ctf.verifier_public_key' => null,
            'lab.runtimes.vulnerable.health_url' => null,
            'lab.runtimes.patched.health_url' => null,
        ]);
    }

    public function test_ctf_page_explains_practice_mode_and_does_not_render_the_flag_before_completion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('PHP Runtime Recon Challenge')
            ->assertSee('http://127.0.0.1:8082/runtime-info.php')
            ->assertSee('http://127.0.0.1:8083/runtime-info.php')
            ->assertSee('not proof of PHP2Shell exploitation')
            ->assertSee('Submit observations')
            ->assertSee('oom_request_parsing.phpt')
            ->assertSee('not a runtime test')
            ->assertDontSee('FLAG{test_php2shell}');
    }

    public function test_correct_live_runtime_versions_complete_the_practice_challenge_and_reveal_flag_to_that_session(): void
    {
        $this->freezeTime();
        $this->fakeExpectedRuntimes();
        $challengeId = $this->startChallenge();

        $this->postJson(route('ctf.practice'), [
            'target_php_version' => '8.5.11',
            'patched_php_version' => '8.5.12',
        ])
            ->assertOk()
            ->assertJsonPath('completed', true);

        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'completed']);
        $this->assertDatabaseHas('ctf_practice_completions', [
            'challenge_id' => $challengeId,
            'target_php_version' => '8.5.11',
            'patched_php_version' => '8.5.12',
        ]);

        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonPath('status', 'practice_completed')
            ->assertJsonPath('completion_type', 'practice')
            ->assertJsonPath('flag', 'FLAG{test_php2shell}')
            ->assertJsonPath('practice_result.target_php_version', '8.5.11')
            ->assertJsonPath('practice_result.patched_php_version', '8.5.12')
            ->assertJsonPath('message', 'Recon practice complete: Laravel confirmed the target and patched-control versions. This is not proof of exploitation or RCE.');
    }

    public function test_wrong_answers_are_rejected_without_completing_or_revealing_flag(): void
    {
        $this->fakeExpectedRuntimes();
        $challengeId = $this->startChallenge();

        $this->postJson(route('ctf.practice'), [
            'target_php_version' => '8.5.12',
            'patched_php_version' => '8.5.11',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'answer_incorrect');

        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'active']);
        $this->assertDatabaseMissing('ctf_practice_completions', ['challenge_id' => $challengeId]);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_practice_completion_rejects_unavailable_or_mismatched_runtime_versions(): void
    {
        $challengeId = $this->startChallenge();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);

        $this->postJson(route('ctf.practice'), ['target_php_version' => '8.5.11', 'patched_php_version' => '8.5.12'])
            ->assertServiceUnavailable()
            ->assertJsonPath('reason', 'runtime_unavailable');

        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'active']);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_practice_completion_rejects_when_live_runtime_versions_do_not_match_expected_releases(): void
    {
        $challengeId = $this->startChallenge();
        config([
            'lab.runtimes.vulnerable.health_url' => 'http://php2shell-vulnerable:8080/runtime-info.php',
            'lab.runtimes.patched.health_url' => 'http://php2shell-patched:8080/runtime-info.php',
        ]);
        Http::fake([
            '*php2shell-vulnerable*' => Http::response(['php_version' => '8.5.10']),
            '*php2shell-patched*' => Http::response(['php_version' => '8.5.12']),
        ]);

        $this->postJson(route('ctf.practice'), ['target_php_version' => '8.5.11', 'patched_php_version' => '8.5.12'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'runtime_version_mismatch');

        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'active']);
        $this->getJson(route('ctf.status'))->assertJsonMissingPath('flag');
    }

    public function test_practice_result_cannot_be_submitted_before_start_or_awarded_twice(): void
    {
        $this->fakeExpectedRuntimes();
        $answers = ['target_php_version' => '8.5.11', 'patched_php_version' => '8.5.12'];

        $this->postJson(route('ctf.practice'), $answers)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'challenge_not_started');

        $challengeId = $this->startChallenge();
        $this->postJson(route('ctf.practice'), $answers)->assertOk();
        $this->postJson(route('ctf.practice'), $answers)
            ->assertStatus(409)
            ->assertJsonPath('reason', 'challenge_not_active');

        $this->assertDatabaseCount('ctf_practice_completions', 1);
        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $challengeId, 'status' => 'completed']);
    }

    public function test_completed_practice_flag_is_not_available_to_a_different_session(): void
    {
        $this->fakeExpectedRuntimes();
        $challengeId = $this->startChallenge();
        $this->postJson(route('ctf.practice'), ['target_php_version' => '8.5.11', 'patched_php_version' => '8.5.12'])->assertOk();

        $this->flushSession();
        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonMissingPath('flag');

        $this->assertDatabaseHas('ctf_practice_completions', ['challenge_id' => $challengeId]);
    }

    public function test_starting_a_challenge_creates_a_session_bound_challenge_record(): void
    {
        $this->freezeTime();

        $response = $this->postJson(route('ctf.start'))
            ->assertOk()
            ->assertJsonPath('started_at', now()->getTimestamp());

        $challengeId = $response->json('challenge_id');

        $this->assertIsString($challengeId);
        $this->assertDatabaseHas('ctf_challenges', [
            'challenge_id' => $challengeId,
            'status' => 'active',
        ]);

        $this->withSession(['ctf_challenge_id' => $challengeId]);

        $this->getJson(route('ctf.status'))
            ->assertOk()
            ->assertJsonPath('challenge_id', $challengeId)
            ->assertJsonPath('status', 'active')
            ->assertJsonMissingPath('flag');
    }

    public function test_starting_a_new_challenge_supersedes_the_previous_active_challenge(): void
    {
        $first = $this->postJson(route('ctf.start'))->json('challenge_id');
        $this->withSession(['ctf_challenge_id' => $first]);
        $second = $this->postJson(route('ctf.start'))->json('challenge_id');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $first, 'status' => 'superseded']);
        $this->assertDatabaseHas('ctf_challenges', ['challenge_id' => $second, 'status' => 'active']);
    }

    public function test_ctf_is_hidden_and_game_endpoints_are_disabled_when_feature_flag_is_off(): void
    {
        config(['lab.ctf.enabled' => false]);

        $this->get('/')->assertDontSee('PHP Runtime Recon Challenge');
        $this->postJson(route('ctf.start'))->assertNotFound();
        $this->getJson(route('ctf.status'))->assertNotFound();
        $this->postJson(route('ctf.practice'))->assertNotFound();
        $this->postJson(route('ctf.attestations.store'))->assertNotFound();
    }

    private function startChallenge(): string
    {
        $challengeId = (string) $this->postJson(route('ctf.start'))->assertOk()->json('challenge_id');
        $this->assertTrue(Str::isUuid($challengeId));

        return $challengeId;
    }

    private function fakeExpectedRuntimes(): void
    {
        config([
            'lab.runtimes.vulnerable.health_url' => 'http://php2shell-vulnerable:8080/runtime-info.php',
            'lab.runtimes.patched.health_url' => 'http://php2shell-patched:8080/runtime-info.php',
        ]);

        Http::fake([
            '*php2shell-vulnerable*' => Http::response(['php_version' => '8.5.11']),
            '*php2shell-patched*' => Http::response(['php_version' => '8.5.12']),
        ]);
    }
}
