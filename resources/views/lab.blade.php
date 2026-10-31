<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#090d0c">
    <title>PHP2Shell Lab</title>
    <style>
        :root { color-scheme: dark; --bg:#090d0c; --panel:#101715; --line:#26332e; --muted:#9ba9a2; --green:#73f0a2; --red:#ff8077; }
        * { box-sizing:border-box }
        body { margin:0; background:radial-gradient(ellipse at 50% -20%,#173024 0,transparent 55%),var(--bg); color:#e8f0eb; font:16px/1.6 ui-sans-serif,system-ui,sans-serif }
        main { width:min(100% - 32px,980px); margin:0 auto; padding:58px 0 72px }
        .eyebrow,.label,.tag { color:var(--green); font:700 .75rem/1.4 ui-monospace,SFMono-Regular,monospace; letter-spacing:.12em; text-transform:uppercase }
        h1 { margin:12px 0; font-size:clamp(2.4rem,7vw,4.4rem); line-height:1.02; letter-spacing:-.055em }
        h1 span { color:var(--green) }
        .intro { max-width:700px; color:#b7c3bc; font-size:1.05rem }
        .notice { margin:28px 0; padding:14px 17px; border:1px solid #3b6148; border-radius:10px; background:#102017; color:#c7e9d0; font-size:.92rem }
        .card { margin-top:22px; border:1px solid var(--line); border-radius:14px; background:#101715; overflow:hidden }
        .card-head { padding:18px 22px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:12px }
        h2 { margin:0; font-size:1.05rem }
        .card-body { padding:22px }
        label { display:block; margin:0 0 8px; font-weight:650 }
        .hint,.muted { color:var(--muted); font-size:.88rem }
        input[type=text] { width:100%; min-height:48px; padding:11px 14px; border:1px solid #34453c; border-radius:8px; background:#080d0b; color:#e8f0eb; font:16px ui-monospace,SFMono-Regular,monospace }
        input:focus { outline:2px solid #59c77d; outline-offset:2px }
        .modes { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:20px 0 }
        .mode { padding:15px; border:1px solid var(--line); border-radius:9px; background:#0c1210; cursor:pointer }
        .mode:has(input:checked) { border-color:#62ce86; box-shadow:inset 0 0 0 1px #62ce8633 }
        .mode input { accent-color:#65df8e }
        .mode-title { display:block; margin:4px 0; font-weight:700 }
        button { border:0; border-radius:8px; padding:12px 18px; background:var(--green); color:#07110a; font-weight:800; cursor:pointer }
        button:hover { background:#a0ffc0 }
        .error { margin-top:8px; color:#ffaaa4; font-size:.88rem }
        .terminal { background:#070b09; font:14px/1.65 ui-monospace,SFMono-Regular,monospace }
        .terminal-top { display:flex; align-items:center; gap:7px; padding:12px 16px; border-bottom:1px solid #202a24; color:#a9b5ae; font-size:.8rem }
        .dot { width:8px; height:8px; border-radius:50%; background:#5d6c63 }
        .terminal-content { padding:18px 20px; white-space:pre-wrap; overflow-wrap:anywhere }
        .prompt { color:var(--green) }
        .status { display:inline-block; margin:3px 0 14px; color:var(--green) }
        .status.bad { color:var(--red) }
        .result-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px }
        .result-item { padding:14px; border:1px solid var(--line); border-radius:9px }
        .result-item p { margin:8px 0 0; color:#c1cdc5; font-size:.92rem }
        textarea,select { width:100%; min-height:48px; padding:11px 14px; border:1px solid #34453c; border-radius:8px; background:#080d0b; color:#e8f0eb; font:15px/1.5 ui-monospace,SFMono-Regular,monospace }
        textarea { min-height:96px; resize:vertical }
        table { width:100%; border-collapse:collapse; margin-top:12px; font-size:.9rem }
        th,td { padding:9px 10px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top }
        th { color:#9ba9a2; font-weight:600; width:34% }
        .flow { padding:16px; border:1px solid #34453c; border-radius:9px; background:#090e0c; color:#cdebd4; font:14px/1.9 ui-monospace,SFMono-Regular,monospace; overflow-wrap:anywhere }
        pre { margin:10px 0 0; padding:13px; border:1px solid #27342c; border-radius:7px; background:#090e0c; color:#cdebd4; white-space:pre-wrap; overflow-wrap:anywhere }
        code { color:#b8f3c8; font:.9em ui-monospace,SFMono-Regular,monospace }
        footer { margin-top:26px; color:#7f8d84; font-size:.82rem; text-align:center }
        @media(max-width:620px) { main{padding-top:36px}.modes,.result-grid{grid-template-columns:1fr}.card-head,.card-body{padding:16px} }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Overview · Local security learning environment</div>
    <h1>PHP2Shell <span>Lab</span></h1>
    <p class="intro">Explore PHP command injection, native session serialization, and object lifecycle behavior in a small controlled security lab.</p>
    <div class="notice"><strong>Contained demonstration.</strong> Command injection remains simulated. Session experiments run in a separate native PHP process in the Docker lab. Deserialization uses one trusted benign class; request data is never deserialized, evaluated, or sent to a shell.</div>

    <section class="card" aria-labelledby="exercise-heading">
        <div class="card-head"><h2 id="exercise-heading">Input exercise</h2><span class="tag">80 characters max</span></div>
        <div class="card-body">
            <form method="post" action="{{ route('lab.run') }}">
                @csrf
                <label for="input">Test input</label>
                <input id="input" name="input" type="text" maxlength="80" required value="{{ old('input', 'hello') }}" placeholder="Try: hello; id" aria-describedby="input-hint">
                <div id="input-hint" class="hint">Try ordinary text, then shell-looking text such as <code>hello; id</code>. The lab describes the effect without running it.</div>
                @error('input') <div class="error">{{ $message }}</div> @enderror
                @error('mode') <div class="error">{{ $message }}</div> @enderror
                <div class="modes" role="radiogroup" aria-label="Implementation to demonstrate">
                    <label class="mode"><input type="radio" name="mode" value="vulnerable" @checked(old('mode', 'vulnerable') === 'vulnerable')><span class="mode-title">Vulnerable pattern</span><span class="hint">Simulate unsafe string concatenation and detect shell syntax.</span></label>
                    <label class="mode"><input type="radio" name="mode" value="secure" @checked(old('mode') === 'secure')><span class="mode-title">Secure processing</span><span class="hint">Allowlist characters and handle the value directly in PHP.</span></label>
                </div>
                <button type="submit">Execute Test</button>
            </form>
        </div>
    </section>

    @if (session('result'))
        @php($result = session('result'))
        <section class="card" aria-labelledby="result-heading" aria-live="polite">
            <div class="card-head"><h2 id="result-heading">Test result</h2><span class="tag">{{ $result['mode'] === 'vulnerable' ? 'Simulation' : 'PHP only' }}</span></div>
            <div class="terminal">
                <div class="terminal-top"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span style="margin-left:8px">php2shell-lab · result</span></div>
                <div class="terminal-content"><span class="prompt">$</span> submitted input: {{ $result['input'] }}
<span class="status {{ str_contains($result['status'], 'Rejected') ? 'bad' : '' }}">status: {{ $result['status'] }}</span>
<span class="prompt">&gt;</span> output:
{{ $result['output'] }}</div>
            </div>
            <div class="card-body">
                <div class="result-grid">
                    <div class="result-item"><div class="label">Why this matters</div><p>{{ $result['explanation'] }}</p></div>
                    <div class="result-item"><div class="label">Recommended remediation</div><p>Avoid shell calls when possible. Validate against a narrow allowlist. If a process is unavoidable, select a fixed executable, pass arguments separately with a process API, enforce a timeout, and never build a shell command from request data.</p></div>
                </div>
            </div>
        </section>
    @endif

    <section class="card" aria-labelledby="execution-heading">
        <div class="card-head"><h2 id="execution-heading">PHP Execution Environment</h2><span class="tag">{{ $executionEnabled ? 'Container enabled' : 'Container only' }}</span></div>
        <div class="card-body">
            <p class="muted">This section starts a real operating-system process from PHP. Choose a name from the fixed allowlist; no command line or arguments are accepted.</p>
            @if ($executionEnabled)
                <form method="post" action="{{ route('lab.execute') }}" style="margin-top:16px">
                    @csrf
                    <label for="command">Harmless allowlisted program</label>
                    <select id="command" name="command" style="width:100%;min-height:48px;padding:11px 14px;border:1px solid #34453c;border-radius:8px;background:#080d0b;color:#e8f0eb;font:16px ui-monospace,SFMono-Regular,monospace">
                        <option value="whoami" @selected(old('command', 'whoami') === 'whoami')>whoami — show the container user</option>
                        <option value="id" @selected(old('command') === 'id')>id — show the container UID and groups</option>
                        <option value="uname" @selected(old('command') === 'uname')>uname — show the kernel name</option>
                    </select>
                    @error('command') <div class="error">{{ $message }}</div> @enderror
                    <p class="hint" style="margin:8px 0 16px">Each process has a 2-second timeout and a 4 KiB combined output limit. It runs without a shell as the unprivileged container user.</p>
                    <button type="submit">Run Allowlisted Process</button>
                </form>
            @else
                <p class="hint" style="margin-top:12px">OS execution is disabled in this runtime. Start with <code>docker compose up --build</code> to enable it inside the isolated container.</p>
            @endif
        </div>
        @if (session('executionResult'))
            @php($executionResult = session('executionResult'))
            <div class="terminal" aria-live="polite">
                <div class="terminal-top"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span style="margin-left:8px">php2shell-lab · process result</span></div>
                <div class="terminal-content"><span class="prompt">$</span> allowlisted program: {{ $executionResult['command'] }}
<span class="status {{ str_contains($executionResult['status'], 'Unavailable') || str_contains($executionResult['status'], 'failed') || str_contains($executionResult['status'], 'Failed') ? 'bad' : '' }}">status: {{ $executionResult['status'] }}</span>
<span class="prompt">&gt;</span> output:
{{ $executionResult['output'] }}</div>
            </div>
        @endif
    </section>

    <section class="card" aria-labelledby="session-heading">
        <div class="card-head"><h2 id="session-heading">Session Configuration &amp; Inspector</h2><span class="tag">Native PHP worker</span></div>
        <div class="card-body">
            <p class="muted">This experiment starts a separate PHP process, independent of Laravel's session middleware. It writes a temporary session, reads the storage bytes, then retrieves the same values using the selected native session serializer.</p>
            @if ($sessionLabEnabled)
                <form method="post" action="{{ route('lab.session') }}" style="margin-top:16px">
                    @csrf
                    <label for="session_format">Session serialization handler</label>
                    <select id="session_format" name="session_format">
                        <option value="php" @selected(old('session_format', 'php') === 'php')>php — key/value session format</option>
                        <option value="php_serialize" @selected(old('session_format') === 'php_serialize')>php_serialize — serialized session array</option>
                    </select>
                    @error('session_format') <div class="error">{{ $message }}</div> @enderror
                    <label for="session_sample" style="margin-top:14px">Sample value (inert data)</label>
                    <textarea id="session_sample" name="session_sample" maxlength="160" required>{{ old('session_sample', 'hello|O:8:"stdClass":0:{}') }}</textarea>
                    <p class="hint">Try punctuation or object-like notation. It is stored only as a string; the worker never deserializes this field.</p>
                    @error('session_sample') <div class="error">{{ $message }}</div> @enderror
                    <button type="submit" style="margin-top:14px">Create &amp; Inspect Session</button>
                </form>
            @else
                <p class="hint" style="margin-top:12px">Session workers are disabled outside the marked Docker lab container.</p>
            @endif

            @if (session('sessionExperiment'))
                @php($sessionExperiment = session('sessionExperiment'))
                <div class="result-item" style="margin-top:18px">
                    <div class="label">{{ $sessionExperiment['status'] }}</div>
                    @if (isset($sessionExperiment['error']))
                        <p>{{ $sessionExperiment['error'] }}</p>
                    @elseif (isset($sessionExperiment['data']))
                        @php($sessionData = $sessionExperiment['data'])
                        <table>
                            <tbody>
                                <tr><th>PHP version</th><td>{{ $sessionData['php_version'] }}</td></tr>
                                <tr><th>Save handler</th><td>{{ $sessionData['session']['save_handler'] }}</td></tr>
                                <tr><th>Serialization handler</th><td>{{ $sessionData['session']['serialize_handler'] }}</td></tr>
                                <tr><th>Storage directory</th><td>{{ $sessionData['session']['save_path'] }}</td></tr>
                                <tr><th>Cookie name</th><td>{{ $sessionData['session']['cookie_name'] }}</td></tr>
                                <tr><th>Strict mode</th><td>{{ $sessionData['session']['strict_mode'] }}</td></tr>
                                <tr><th>Cookies used by worker</th><td>{{ $sessionData['session']['cookies_enabled'] }}</td></tr>
                            </tbody>
                        </table>
                        <div class="label" style="margin-top:16px">Raw session storage</div>
                        <pre>{{ $sessionData['raw_storage'] }}</pre>
                        <div class="label" style="margin-top:16px">Retrieved values</div>
                        <pre>{{ json_encode($sessionData['retrieved'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        <p class="hint">The saved session ID was {{ $sessionData['session_id'] }}. The sample returned as a string: {{ $sessionData['sample_is_plain_data'] ? 'yes' : 'no' }}. Temporary session files are removed after the experiment.</p>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="card" aria-labelledby="serialization-heading">
        <div class="card-head"><h2 id="serialization-heading">Serialization Tester</h2><span class="tag">Strings remain data</span></div>
        <div class="card-body">
            <p class="muted">PHP's <code>serialize()</code> encodes a value into a representation. This tester wraps your sample in a fixed array and serializes that array; it does not call <code>unserialize()</code> on request data.</p>
            <form method="post" action="{{ route('lab.serialize') }}" style="margin-top:16px">
                @csrf
                <label for="serialization_sample">Sample text</label>
                <textarea id="serialization_sample" name="serialization_sample" maxlength="180" required>{{ old('serialization_sample', 'O:8:"stdClass":0:{}') }}</textarea>
                @error('serialization_sample') <div class="error">{{ $message }}</div> @enderror
                <button type="submit" style="margin-top:14px">Serialize Sample</button>
            </form>
            @if (session('serializationResult'))
                @php($serializationResult = session('serializationResult'))
                <div class="result-item" style="margin-top:18px">
                    <div class="label">{{ $serializationResult['status'] }}</div>
                    <p>Input: {{ $serializationResult['input'] }}</p>
                    <pre>{{ $serializationResult['serialized'] }}</pre>
                </div>
            @endif
        </div>
    </section>

    <section class="card" aria-labelledby="object-heading">
        <div class="card-head"><h2 id="object-heading">Object Deserialization</h2><span class="tag">Trusted class only</span></div>
        <div class="card-body">
            <p class="muted">The app generates a trusted serialized fixture for one predefined class. Its constructor, <code>__wakeup()</code>, and <code>__destruct()</code> only record lifecycle events in the Laravel log. The fixture has no command, file, or network behavior.</p>
            @if ($sessionLabEnabled)
                <form method="post" action="{{ route('lab.object-lifecycle') }}" style="margin-top:16px">
                    @csrf
                    <button type="submit">Run Benign Lifecycle Demo</button>
                </form>
            @else
                <p class="hint" style="margin-top:12px">Object deserialization is disabled outside the marked Docker lab container.</p>
            @endif
            @if (session('lifecycleResult'))
                @php($lifecycleResult = session('lifecycleResult'))
                <div class="result-item" style="margin-top:18px">
                    <div class="label">{{ $lifecycleResult['status'] }}</div>
                    @if (isset($lifecycleResult['error']))
                        <p>{{ $lifecycleResult['error'] }}</p>
                    @elseif (isset($lifecycleResult['data']))
                        <p>Restored class: {{ $lifecycleResult['data']['restored_object']['class'] }} · fixture label: {{ $lifecycleResult['data']['restored_object']['label'] }}</p>
                        <div class="label" style="margin-top:14px">Trusted serialized fixture</div>
                        <pre>{{ $lifecycleResult['data']['fixture'] }}</pre>
                        <div class="label" style="margin-top:14px">Logged lifecycle events</div>
                        <pre>@foreach ($lifecycleResult['data']['events'] as $event){{ $event['event'] }} — {{ $event['label'] }}
@endforeach</pre>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <section class="card" aria-labelledby="flow-heading">
        <div class="card-head"><h2 id="flow-heading">Exploitation Flow</h2><span class="tag">Theory · simulated risk</span></div>
        <div class="card-body">
            <div class="flow">HTTP request → session data handling → serialization parsing → object deserialization → dangerous magic method → potential RCE</div>
            <p class="muted" style="margin-top:12px">This chain is an explanation of a class of vulnerability, not a demonstrated RCE. This lab uses an app-generated fixture and benign logging methods. It never deserializes request data and never invokes a process from a magic method.</p>
        </div>
    </section>

    <section class="card" aria-labelledby="analysis-heading">
        <div class="card-head"><h2 id="analysis-heading">Security Analysis</h2><span class="tag">Remediation</span></div>
        <div class="card-body">
            <div class="result-grid">
                <div class="result-item"><div class="label">Detection</div><p>Identify where session bytes are parsed, which serialization handler is active, and whether object restoration can invoke application magic methods. A serialized-looking string alone is not proof of code execution.</p></div>
                <div class="result-item"><div class="label">Remediation</div><p>Do not deserialize untrusted data. Prefer JSON or another data-only format. If PHP deserialization is unavoidable, use a strict class allowlist, review magic methods and dependencies, authenticate data before parsing, and keep PHP and packages patched.</p></div>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="execution-comparison-heading">
        <div class="card-head"><h2 id="execution-comparison-heading">Simulation vs. process execution</h2><span class="tag">Two different behaviors</span></div>
        <div class="card-body">
            <div class="result-grid">
                <div class="result-item"><div class="label">PHP simulator</div><p>Examines input characters in PHP and describes what unsafe shell concatenation could mean. It starts no process, so shell interpretation is not actually occurring.</p></div>
                <div class="result-item"><div class="label">PHP execution environment</div><p>PHP starts a real allowlisted executable using Symfony Process with an argument array. The program runs in the container and returns its output; no shell parses request data.</p></div>
            </div>
            <p class="muted" style="margin:16px 0 0"><code>shell_exec()</code> normally passes a command string through a shell and returns its output, so concatenating untrusted input can alter the shell program. This lab demonstrates the operating-system boundary with constrained process execution while keeping the injectable shell-string example simulated.</p>
        </div>
    </section>

    <section class="card" aria-labelledby="comparison-heading">
        <div class="card-head"><h2 id="comparison-heading">Code comparison</h2><span class="tag">For study · not executed</span></div>
        <div class="card-body">
            <div class="result-grid">
                <div class="result-item"><div class="label">Vulnerable pattern</div><p>Concatenation gives the shell control over the meaning of input.</p><pre><code>// Unsafe: illustrative only
$command = "printf '%s\\n' 'Hello, " . $input . "'";
$output = shell_exec($command);</code></pre></div>
                <div class="result-item"><div class="label">Safer pattern</div><p>Prefer native PHP behavior. Validation is specific to the expected data.</p><pre><code>// This lab's approach
if (preg_match('/\\A[a-zA-Z0-9 ]{1,80}\\z/', $input)) {
    $output = 'Hello, ' . $input . '!';
}</code></pre></div>
            </div>
            <p class="muted" style="margin:16px 0 0">The function names <code>shell_exec()</code>, <code>exec()</code>, and <code>system()</code> all cross into operating-system command execution. Their behavior differs from the argument-array process API used in the container demo.</p>
        </div>
    </section>
    <footer>PHP2Shell Lab · Local education only · Real processes are limited to the isolated Docker container and fixed allowlist</footer>
</main>
</body>
</html>
