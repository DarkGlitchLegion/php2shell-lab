<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionLabTest extends TestCase
{
    public function test_existing_command_injection_simulator_remains_non_executing(): void
    {
        $this->from('/')->post(route('lab.run'), [
            'input' => 'hello; id',
            'mode' => 'vulnerable',
        ])->assertRedirect('/')
            ->assertSessionHas('result.status', 'Injection pattern detected (simulated)')
            ->assertSessionHas('result.output', fn (string $output): bool => str_contains($output, 'No command was started'));
    }

    public function test_secure_mode_rejects_shell_metacharacters(): void
    {
        $this->from('/')->post(route('lab.run'), [
            'input' => 'hello; id',
            'mode' => 'secure',
        ])->assertRedirect('/')
            ->assertSessionHas('result.status', 'Rejected by validation');
    }

    public function test_session_format_is_allowlisted_before_worker_start(): void
    {
        $this->from('/')->post(route('lab.session'), [
            'session_format' => 'user-handler',
            'session_sample' => 'plain text',
        ])->assertSessionHasErrors('session_format');
    }

    public function test_session_worker_is_disabled_by_default_outside_docker(): void
    {
        config(['lab.session_lab_enabled' => false]);

        $this->from('/')->post(route('lab.session'), [
            'session_format' => 'php',
            'session_sample' => 'plain text',
        ])->assertRedirect('/')
            ->assertSessionHas('sessionExperiment.status', 'Unavailable outside the isolated lab container');
    }

    public function test_serialized_looking_request_text_is_only_wrapped_as_string_data(): void
    {
        $sample = 'O:8:"stdClass":0:{}';

        $this->from('/')->post(route('lab.serialize'), [
            'serialization_sample' => $sample,
        ])->assertRedirect('/')
            ->assertSessionHas('serializationResult.input', $sample)
            ->assertSessionHas('serializationResult.serialized', serialize(['sample' => $sample]))
            ->assertSessionHas('serializationResult.status', 'Serialized as data only; user input was not deserialized or evaluated');
    }

    public function test_php_source_text_is_not_executed_by_the_serialization_tester(): void
    {
        $sample = '<?php echo "inert sample"; ?>';

        $this->from('/')->post(route('lab.serialize'), [
            'serialization_sample' => $sample,
        ])->assertRedirect('/')
            ->assertSessionHas('serializationResult.input', $sample)
            ->assertSessionHas('serializationResult.serialized', serialize(['sample' => $sample]));
    }

    public function test_disabled_execution_configuration_hides_process_form(): void
    {
        config([
            'lab.os_execution_enabled' => false,
            'lab.session_lab_enabled' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('OS execution is disabled in this runtime.')
            ->assertSee('Session workers are disabled outside the marked Docker lab container.')
            ->assertDontSee('name="command"');
    }

    public function test_arbitrary_process_command_is_rejected_before_execution(): void
    {
        $this->from('/')->post(route('lab.execute'), [
            'command' => 'id; whoami',
        ])->assertSessionHasErrors('command');
    }

    public function test_object_lifecycle_endpoint_is_disabled_outside_docker(): void
    {
        config(['lab.session_lab_enabled' => false]);

        $this->from('/')->post(route('lab.object-lifecycle'))
            ->assertRedirect('/')
            ->assertSessionHas('lifecycleResult.status', 'Unavailable outside the isolated lab container');
    }
}
