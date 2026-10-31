<?php

namespace Tests\Feature;

use Tests\TestCase;

class CtfGameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lab.ctf.enabled' => true,
            'lab.ctf.flag' => 'FLAG{test_php2shell}',
            'lab.runtimes.vulnerable.health_url' => null,
            'lab.runtimes.patched.health_url' => null,
        ]);
    }

    public function test_practice_round_is_available_with_local_targets_and_unverified_score_notice(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('CTF Practice Round')
            ->assertSee('http://127.0.0.1:8082/runtime-info.php')
            ->assertSee('http://127.0.0.1:8083/runtime-info.php')
            ->assertSee('not a verified competition result')
            ->assertDontSee('FLAG{test_php2shell}');
    }

    public function test_game_is_hidden_and_endpoints_are_disabled_when_feature_flag_is_off(): void
    {
        config(['lab.ctf.enabled' => false]);

        $this->get('/')->assertDontSee('CTF Practice Round');

        $this->postJson(route('ctf.start'))->assertNotFound();
        $this->postJson(route('ctf.submit'), ['flag' => 'FLAG{test_php2shell}'])->assertNotFound();
    }

    public function test_starting_a_round_records_its_server_start_time(): void
    {
        $this->travelTo('2026-10-31 10:00:00');

        $this->postJson(route('ctf.start'))
            ->assertOk()
            ->assertJsonPath('started_at', now()->getTimestamp())
            ->assertSessionHas('ctf_round_started_at', now()->getTimestamp());
    }

    public function test_flag_submission_requires_an_active_round(): void
    {
        $this->postJson(route('ctf.submit'), ['flag' => 'FLAG{test_php2shell}'])
            ->assertStatus(409)
            ->assertJsonPath('solved', false)
            ->assertJsonPath('message', 'Start a round before submitting a flag.');
    }

    public function test_wrong_flag_is_rejected_and_keeps_the_round_active(): void
    {
        $this->postJson(route('ctf.start'))->assertOk();

        $this->postJson(route('ctf.submit'), ['flag' => 'FLAG{wrong}'])
            ->assertUnprocessable()
            ->assertJsonPath('solved', false)
            ->assertJsonPath('message', 'That flag is not correct yet. The timer is still running.')
            ->assertSessionHas('ctf_round_started_at');
    }

    public function test_correct_flag_completes_round_with_elapsed_seconds(): void
    {
        $this->travelTo('2026-10-31 10:00:00');
        $this->postJson(route('ctf.start'))->assertOk();
        $this->travel(17)->seconds();

        $this->postJson(route('ctf.submit'), ['flag' => 'FLAG{test_php2shell}'])
            ->assertOk()
            ->assertJsonPath('solved', true)
            ->assertJsonPath('elapsed_seconds', 17)
            ->assertJsonPath('message', 'Flag accepted. This is a local practice score, not verified proof of exploitation.')
            ->assertSessionMissing('ctf_round_started_at');
    }

    public function test_flag_validation_rejects_values_longer_than_the_supported_limit(): void
    {
        $this->postJson(route('ctf.start'))->assertOk();

        $this->postJson(route('ctf.submit'), ['flag' => str_repeat('F', 129)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('flag');
    }
}
