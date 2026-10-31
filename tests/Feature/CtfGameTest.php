<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    public function test_ctf_page_explains_verifier_gate_and_does_not_render_the_flag_before_completion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Attested CTF Round')
            ->assertSee('http://127.0.0.1:8082/runtime-info.php')
            ->assertSee('http://127.0.0.1:8083/runtime-info.php')
            ->assertSee('trusted signed attestation')
            ->assertSee('oom_request_parsing.phpt')
            ->assertSee('not a runtime test')
            ->assertDontSee('FLAG{test_php2shell}');
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

        $this->get('/')->assertDontSee('Attested CTF Round');
        $this->postJson(route('ctf.start'))->assertNotFound();
        $this->getJson(route('ctf.status'))->assertNotFound();
        $this->postJson(route('ctf.attestations.store'))->assertNotFound();
    }
}
