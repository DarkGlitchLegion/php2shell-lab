<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Log;
use Php2ShellLab\Serialization\ObjectLifecycleExperiment;
use Tests\TestCase;

class ObjectLifecycleExperimentTest extends TestCase
{
    public function test_trusted_fixture_invokes_only_benign_magic_methods_and_logs_lifecycle(): void
    {
        Log::spy();

        $result = (new ObjectLifecycleExperiment)->run();

        $this->assertSame([
            '__construct',
            '__destruct',
            '__wakeup',
            '__destruct',
        ], array_column($result['events'], 'event'));
        $this->assertSame('trusted-lifecycle-fixture', $result['restored_object']['label']);
        $this->assertStringStartsWith('O:', $result['fixture']);
        Log::shouldHaveReceived('info')->times(4);
    }
}
