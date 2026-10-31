<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Php2ShellLab\Serialization\ObjectLifecycleExperiment;
use Php2ShellLab\Session\SessionExperiment;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class LabController extends Controller
{
    private const MAX_OUTPUT_BYTES = 4096;

    private const PROCESS_TIMEOUT_SECONDS = 2;

    public function index(): View
    {
        return view('lab', [
            'executionEnabled' => $this->osExecutionAvailable(),
            'sessionLabEnabled' => $this->sessionLabAvailable(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'max:80'],
            'mode' => ['required', 'in:vulnerable,secure'],
        ]);

        $input = trim($validated['input']);
        $mode = $validated['mode'];

        if ($mode === 'vulnerable') {
            $hasShellSyntax = preg_match('/[;&|`$()<>\\\\\n\r]/', $input) === 1;

            $result = [
                'input' => $input,
                'mode' => $mode,
                'status' => $hasShellSyntax ? 'Injection pattern detected (simulated)' : 'Completed (simulated)',
                'output' => $hasShellSyntax
                    ? "[SIMULATION ONLY] The concatenated command would treat shell syntax in this input as control syntax. No command was started and no operating-system command was run."
                    : "[SIMULATION ONLY] A command such as `printf '%s\\n' 'Hello, {$input}'` would be constructed. This lab does not execute it.",
                'explanation' => 'Directly appending untrusted text to a shell command lets the shell interpret metacharacters as syntax. The displayed vulnerable snippet is intentionally not executed.',
            ];
        } else {
            $valid = preg_match('/\A[a-zA-Z0-9 ]{1,80}\z/', $input) === 1;

            $result = [
                'input' => $input,
                'mode' => $mode,
                'status' => $valid ? 'Accepted by allowlist' : 'Rejected by validation',
                'output' => $valid
                    ? "Hello, {$input}! This response was built directly in PHP; no shell was used."
                    : 'Input must contain only letters, numbers, and spaces. No command was run.',
                'explanation' => 'The secure path accepts only a small allowlist and builds the response in PHP. Prefer avoiding shell execution; if a process is essential, use a fixed executable and safely separated arguments.',
            ];
        }

        return redirect()->route('lab')->with('result', $result)->withInput();
    }

    public function execute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'command' => ['required', 'string', 'in:whoami,id,uname'],
        ]);

        $command = $validated['command'];

        if (! $this->osExecutionAvailable()) {
            return redirect()->route('lab')->with('executionResult', [
                'command' => $command,
                'status' => 'Unavailable outside the isolated lab container',
                'output' => 'No process was started. Use the Docker Compose environment with OS execution enabled.',
            ]);
        }

        $executables = [
            'whoami' => '/usr/bin/whoami',
            'id' => '/usr/bin/id',
            'uname' => '/usr/bin/uname',
        ];

        $process = new Process([$executables[$command]], '/tmp');
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->disableOutput();

        $output = '';
        $truncated = false;
        $timedOut = false;
        $exitCode = null;

        try {
            $exitCode = $process->run(function (string $type, string $chunk) use ($process, &$output, &$truncated): void {
                $remaining = self::MAX_OUTPUT_BYTES - strlen($output);

                if ($remaining > 0) {
                    $output .= substr($chunk, 0, $remaining);
                }

                if (strlen($chunk) > $remaining) {
                    $truncated = true;
                    $process->stop(0);
                }
            });
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        } catch (Throwable) {
            $output = 'The fixed demonstration process could not be started.';
        }

        if ($truncated) {
            $output .= "\n[Output truncated at ".self::MAX_OUTPUT_BYTES.' bytes.]';
            $status = 'Stopped at output limit';
        } elseif ($timedOut) {
            $status = 'Timed out after '.self::PROCESS_TIMEOUT_SECONDS.' seconds';
        } elseif ($exitCode === 0) {
            $status = 'Completed successfully (exit code 0)';
        } elseif ($exitCode !== null) {
            $status = "Process failed (exit code {$exitCode})";
        } else {
            $status = 'Failed to start';
        }

        return redirect()->route('lab')->with('executionResult', [
            'command' => $command,
            'status' => $status,
            'output' => $output === '' ? '[No output]' : $output,
        ]);
    }

    public function sessionExperiment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_format' => ['required', 'in:php,php_serialize'],
            'session_sample' => ['required', 'string', 'max:160'],
        ]);

        if (! $this->sessionLabAvailable()) {
            return redirect()->route('lab')->with('sessionExperiment', [
                'status' => 'Unavailable outside the isolated lab container',
                'error' => 'No native PHP session worker was started.',
            ])->withInput();
        }

        try {
            $result = (new SessionExperiment)->run(
                $validated['session_format'],
                $validated['session_sample'],
            );

            return redirect()->route('lab')->with('sessionExperiment', [
                'status' => 'Session created and retrieved successfully',
                'format' => $validated['session_format'],
                'data' => $result,
            ])->withInput();
        } catch (Throwable $exception) {
            Log::warning('PHP2Shell isolated session experiment failed', [
                'exception' => $exception::class,
            ]);

            return redirect()->route('lab')->with('sessionExperiment', [
                'status' => 'Session experiment failed',
                'error' => 'The isolated PHP session worker did not complete. Check the application log.',
            ])->withInput();
        }
    }

    public function serializeSample(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'serialization_sample' => ['required', 'string', 'max:180'],
        ]);

        $serialized = serialize([
            'sample' => $validated['serialization_sample'],
        ]);

        return redirect()->route('lab')->with('serializationResult', [
            'input' => $validated['serialization_sample'],
            'serialized' => $serialized,
            'status' => 'Serialized as data only; user input was not deserialized or evaluated',
        ])->withInput();
    }

    public function objectLifecycle(): RedirectResponse
    {
        if (! $this->sessionLabAvailable()) {
            return redirect()->route('lab')->with('lifecycleResult', [
                'status' => 'Unavailable outside the isolated lab container',
                'error' => 'No object fixture was deserialized.',
            ]);
        }

        try {
            $result = (new ObjectLifecycleExperiment)->run();

            return redirect()->route('lab')->with('lifecycleResult', [
                'status' => 'Trusted benign fixture deserialized; lifecycle events logged',
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            Log::warning('PHP2Shell benign object lifecycle experiment failed', [
                'exception' => $exception::class,
            ]);

            return redirect()->route('lab')->with('lifecycleResult', [
                'status' => 'Object lifecycle experiment failed',
                'error' => 'The benign fixture experiment did not complete. Check the application log.',
            ]);
        }
    }

    private function osExecutionAvailable(): bool
    {
        return config('lab.os_execution_enabled') === true
            && is_file('/.php2shell-lab-container');
    }

    private function sessionLabAvailable(): bool
    {
        return config('lab.session_lab_enabled') === true
            && is_file('/.php2shell-lab-container');
    }
}
