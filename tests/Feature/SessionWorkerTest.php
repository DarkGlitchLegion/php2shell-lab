<?php

namespace Tests\Feature;

use Php2ShellLab\Session\SessionExperiment;
use Tests\TestCase;

class SessionWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file('/.php2shell-lab-container')) {
            $this->markTestSkipped('Native session worker integration tests run only in the isolated Docker test container.');
        }
    }

    public function test_native_session_is_created_and_retrieved_with_both_serializers(): void
    {
        $sample = 'first|O:8:"stdClass":0:{}';
        $experiment = new SessionExperiment;

        $php = $experiment->run('php', $sample);
        $phpSerialize = $experiment->run('php_serialize', $sample);

        $this->assertSame('php', $php['session']['serialize_handler']);
        $this->assertSame('php_serialize', $phpSerialize['session']['serialize_handler']);
        $this->assertSame($sample, $php['retrieved']['sample_input']);
        $this->assertSame($sample, $phpSerialize['retrieved']['sample_input']);
        $this->assertTrue($php['sample_is_plain_data']);
        $this->assertTrue($phpSerialize['sample_is_plain_data']);
        $this->assertStringStartsWith('sample_input|s:', $php['raw_storage']);
        $this->assertStringStartsWith('a:2:{', $phpSerialize['raw_storage']);
        $this->assertSame('files', $php['session']['save_handler']);
        $this->assertSame('PHP2SHELLSESSID', $php['session']['cookie_name']);
        $this->assertSame('1', $php['session']['strict_mode']);
        $this->assertSame('0', $php['session']['cookies_enabled']);
    }

    public function test_unapproved_serialization_handler_is_rejected_without_starting_worker(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SessionExperiment)->run('user-defined-handler', 'plain text');
    }
}
