<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RuntimeStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lab.runtimes.vulnerable.health_url' => 'http://php2shell-vulnerable:8080/runtime-info.php',
            'lab.runtimes.patched.health_url' => 'http://php2shell-patched:8080/runtime-info.php',
        ]);
    }

    public function test_dashboard_identifies_both_cves_and_requires_trusted_attestation_for_rce_status(): void
    {
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 503)]);

        $this->get('/')
            ->assertOk()
            ->assertSee('CVE-2026-109618')
            ->assertSee('CVE-2026-109619')
            ->assertSee('Vulnerable · PHP 8.5.11')
            ->assertSee('Patched · PHP 8.5.12')
            ->assertSee('RCE status comes only from a trusted signed attestation')
            ->assertSee('not proof of RCE');
    }

    public function test_runtime_status_compares_exact_versions_and_reports_mismatches(): void
    {
        Http::fake([
            'php2shell-vulnerable:8080/*' => Http::response([
                'php_version' => '8.5.11',
                'sapi' => 'apache2handler',
                'container_started_at' => '2026-10-31T00:00:00+00:00',
            ]),
            'php2shell-patched:8080/*' => Http::response([
                'php_version' => '8.5.13',
                'sapi' => 'apache2handler',
                'container_started_at' => '2026-10-31T00:00:00+00:00',
            ]),
        ]);

        $this->getJson('/runtime-status')
            ->assertOk()
            ->assertJsonPath('vulnerable.status', 'Online')
            ->assertJsonPath('vulnerable.version_status', 'Expected pre-patch build')
            ->assertJsonPath('patched.status', 'Online')
            ->assertJsonPath('patched.version_status', 'Version mismatch');
    }

    public function test_runtime_status_marks_unreachable_environment_unavailable(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->getJson('/runtime-status')
            ->assertOk()
            ->assertJsonPath('vulnerable.status', 'Unavailable')
            ->assertJsonPath('patched.status', 'Unavailable');
    }
}
