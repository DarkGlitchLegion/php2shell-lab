<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabController extends Controller
{
    public function index(): View
    {
        return view('lab');
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
}
