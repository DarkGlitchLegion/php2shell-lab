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
        .runtime-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px }
        .runtime-card { min-width:0; padding:16px; border:1px solid var(--line); border-radius:10px; background:#0c1210 }
        .runtime-title { display:flex; justify-content:space-between; align-items:center; gap:10px }
        .runtime-status { color:var(--green); font:700 .78rem ui-monospace,SFMono-Regular,monospace }
        .runtime-status.bad { color:var(--red) }
        .tag.bad { color:var(--red) }
        .terminal-tabs { display:flex; flex-wrap:wrap; gap:10px; padding:16px 20px; border-bottom:1px solid var(--line) }
        .terminal-tabs button, .terminal-actions button { padding:9px 13px; background:#1a2a21; color:#d6f4de; border:1px solid #355441 }
        .terminal-tabs button[aria-pressed="true"] { background:var(--green); color:#07110a }
        .terminal-frame { display:block; width:100%; height:420px; border:0; background:#070b09 }
        .terminal-frame[hidden] { display:none }
        .terminal-actions { display:flex; flex-wrap:wrap; gap:10px; padding:12px 20px; border-top:1px solid var(--line) }
        .terminal-actions .reset { border-color:#70413e; color:#ffc4bf; background:#281312 }
        details.legacy-demos { margin-top:28px; border:1px solid var(--line); border-radius:12px; background:#0c1210 }
        details.legacy-demos > summary { padding:16px 20px; color:var(--green); font-weight:750; cursor:pointer }
        details.legacy-demos > section { margin:0 14px 18px }
        pre { margin:10px 0 0; padding:13px; border:1px solid #27342c; border-radius:7px; background:#090e0c; color:#cdebd4; white-space:pre-wrap; overflow-wrap:anywhere }
        code { color:#b8f3c8; font:.9em ui-monospace,SFMono-Regular,monospace }
        footer { margin-top:26px; color:#7f8d84; font-size:.82rem; text-align:center }
        @media(max-width:620px) { main{padding-top:36px}.modes,.result-grid,.runtime-grid{grid-template-columns:1fr}.card-head,.card-body{padding:16px}.terminal-frame{height:340px} }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Overview · Local PHP runtime research</div>
    <h1>PHP2Shell <span>Lab</span></h1>
    <p class="intro">Compare the affected PHP 8.5.11 runtime with the patched PHP 8.5.12 runtime for CVE-2026-109618 and CVE-2026-109619. Inspect the real Apache and PHP environments in isolated Docker containers.</p>
    <div class="notice"><strong>Version comparison is not exploit verification.</strong> This lab does not send vulnerability payloads or claim to reproduce either CVE. The affected image is an intentionally vulnerable runtime; keep it local and disposable.</div>

    <section class="card" id="overview" aria-labelledby="overview-heading">
        <div class="card-head"><h2 id="overview-heading">Overview</h2><span class="tag">Two exact PHP releases</span></div>
        <div class="card-body">
            <p class="muted">The PHP project identifies 8.5.11 as a pre-fix release and 8.5.12 as the release containing the fixes. Normal HTTP requests are handled by Apache with mod_php in both environments. They use separate container filesystems, internal Docker networks, and resource limits.</p>
            <p class="hint">A version match indicates the runtime release only. Configuration, request handling, build differences, and deployment conditions affect exposure; no exploit success is inferred.</p>
        </div>
    </section>

    <section class="card" id="vulnerability-information" aria-labelledby="cve-heading">
        <div class="card-head"><h2 id="cve-heading">Vulnerability Information</h2><span class="tag">PHP runtime defects</span></div>
        <div class="card-body result-grid">
            <article class="result-item">
                <div class="label">CVE-2026-109618 · OOM cleanup corruption</div>
                <p>PHP’s out-of-memory handling can interrupt request parsing while internal request data is only partly initialized. Later request cleanup may treat that incomplete state as valid, leading to memory corruption and potentially code execution.</p>
            </article>
            <article class="result-item">
                <div class="label">CVE-2026-109619 · stale request state</div>
                <p>Request-owned internal state can remain reachable after its backing memory is freed. A later request handled by the worker may use the stale state before normal initialization resets it, creating a use-after-free and possible memory corruption.</p>
            </article>
        </div>
        <div class="card-body" style="padding-top:0">
            <p class="hint">These are PHP engine memory-safety flaws. They are distinct from Laravel command injection, PHP object deserialization, and session-file poisoning.</p>
            <p class="hint">References: <a href="https://www.php.net/archive/2026.php#2026-10-21-5" rel="noreferrer">PHP security advisory</a> · <a href="https://www.wiz.io/blog/php2shell-php-rce" rel="noreferrer">Wiz Research technical report</a></p>
        </div>
    </section>

    <section class="card" id="runtime-information" aria-labelledby="runtime-heading">
        <div class="card-head"><h2 id="runtime-heading">Runtime Information &amp; Health</h2><span class="tag">HTTP health checks · refresh every 10s</span></div>
        <div class="card-body runtime-grid" id="runtime-grid" data-status-url="{{ route('lab.runtime-status') }}">
            @foreach ($runtimes as $key => $runtime)
                @php($info = $runtime['info'])
                <article class="runtime-card" data-runtime="{{ $key }}">
                    <div class="runtime-title">
                        <strong>{{ $runtime['label'] }}</strong>
                        <span class="runtime-status {{ $runtime['status'] === 'Online' && $runtime['version_status'] !== 'Version mismatch' ? '' : 'bad' }}" data-runtime-status>{{ $runtime['status'] }}</span>
                    </div>
                    <p class="hint" data-version-status>{{ $runtime['version_status'] }}</p>
                    <table>
                        <tbody>
                            <tr><th>Container</th><td>{{ $runtime['container_name'] }}</td></tr>
                            <tr><th>Expected / actual PHP</th><td data-php-version>{{ $runtime['expected_version'] }} / {{ $info['php_version'] ?? '—' }}</td></tr>
                            <tr><th>PHP SAPI</th><td data-sapi>{{ $info['sapi'] ?? '—' }}</td></tr>
                            <tr><th>Apache</th><td data-apache>{{ $info['apache_version'] ?? '—' }}</td></tr>
                            <tr><th>Terminal service</th><td data-runtime-terminal>{{ $info['terminal_status'] ?? '—' }}</td></tr>
                            <tr><th>Zend build</th><td data-zend>{{ $info['zend_version'] ?? '—' }}</td></tr>
                            <tr><th>Build</th><td data-build>{{ isset($info['build']) ? ($info['build']['os_family'].' · '.$info['build']['architecture'].' · '.($info['build']['zts'] ? 'ZTS' : 'NTS')) : '—' }}</td></tr>
                            <tr><th>Started</th><td data-started-at>{{ $info['container_started_at'] ?? '—' }}</td></tr>
                            <tr><th>PHP configuration</th><td data-configuration>@if ($info) @foreach ($info['configuration'] as $name => $value){{ $name }}={{ $value }}@if (!$loop->last); @endif @endforeach @else — @endif</td></tr>
                            <tr><th>Loaded extensions</th><td data-extensions>{{ $info ? implode(', ', $info['loaded_extensions']) : '—' }}</td></tr>
                        </tbody>
                    </table>
                </article>
            @endforeach
        </div>
        <div class="card-body" style="padding-top:0">
            <p class="hint">If a runtime stops responding, its state changes to Unavailable. A changed start time after recovery indicates that the disposable container restarted. Health checks report release and process availability; they do not trigger CVE code paths.</p>
            <p class="hint">Apache access, error, and PHP error logs live in each container’s temporary log filesystem. Open the matching terminal and use <code>tail -n 50 /var/log/apache2/access.log</code>, <code>tail -n 50 /var/log/apache2/error.log</code>, or <code>tail -n 50 /var/log/apache2/php-error.log</code>.</p>
        </div>
    </section>

    <section class="card" id="terminal" aria-labelledby="terminal-heading">
        <div class="card-head"><h2 id="terminal-heading">Real Terminal</h2><span class="tag">ttyd · <span class="runtime-status" data-terminal-status>Checking connection…</span></span></div>
        <div class="terminal-tabs" role="tablist" aria-label="Select PHP runtime terminal">
            <button type="button" role="tab" aria-selected="true" aria-pressed="true" data-select-terminal="vulnerable">Vulnerable · PHP 8.5.11</button>
            <button type="button" role="tab" aria-selected="false" aria-pressed="false" data-select-terminal="patched">Patched · PHP 8.5.12</button>
        </div>
        @foreach ($runtimes as $key => $runtime)
            <iframe class="terminal-frame" title="{{ $runtime['label'] }} Linux terminal" data-terminal-frame="{{ $key }}" src="{{ $runtime['terminal_url'] }}" @if ($key !== 'vulnerable') hidden @endif></iframe>
        @endforeach
        <div class="terminal-actions">
            <button type="button" data-clear-terminal>Clear / reconnect terminal</button>
            <button type="button" class="reset" data-copy-reset="docker compose restart php2shell-vulnerable">Copy vulnerable-container reset command</button>
            <button type="button" class="reset" data-copy-reset="docker compose restart php2shell-patched">Copy patched-container reset command</button>
        </div>
        <div class="card-body" style="padding-top:0">
            <p class="hint">This is a real interactive Bash terminal with command history. Its shell is inside the selected container. The terminal service is loopback-published only, accepts one local client, checks the browser origin, and runs without root. Clearing reconnects the shell; restart commands must be pasted into a Kali terminal and do not run through Laravel.</p>
            <p class="hint" id="terminal-action-status" aria-live="polite">Try <code>php -v</code>, <code>php --ini</code>, <code>apache2 -v</code>, <code>ps -ef</code>, <code>pwd</code>, or inspect the logs listed above.</p>
        </div>
    </section>

    <details class="legacy-demos">
        <summary>Existing safe PHP command, session, and serialization exercises (not tests for these CVEs)</summary>
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
    </details>
    <footer>PHP2Shell Lab · Local-only runtime comparison · No exploit payloads or arbitrary host command endpoint</footer>
</main>
<script>
(() => {
    const grid = document.getElementById('runtime-grid');
    const statusLine = document.getElementById('terminal-action-status');
    const panels = [...document.querySelectorAll('[data-terminal-frame]')];
    const tabs = [...document.querySelectorAll('[data-select-terminal]')];
    const statusUrl = grid?.dataset.statusUrl;
    const terminalStatus = document.querySelector('[data-terminal-status]');

    const renderRuntime = (key, runtime) => {
        const card = document.querySelector(`[data-runtime="${key}"]`);
        if (!card) return;
        const put = (selector, value) => {
            const target = card.querySelector(selector);
            if (target && value !== undefined && value !== null && value !== '') target.textContent = value;
        };
        const info = runtime.info || {};
        const storageKey = `php2shell-${key}-started`;
        const restartKey = `php2shell-${key}-restart-detected`;
        const previousStart = localStorage.getItem(storageKey);
        const newStart = info.container_started_at || '';
        const restarted = previousStart && newStart && previousStart !== newStart;
        if (newStart) localStorage.setItem(storageKey, newStart);
        if (restarted) localStorage.setItem(restartKey, newStart);
        const status = runtime.status + (newStart && localStorage.getItem(restartKey) === newStart ? ' · restarted' : '');
        const statusNode = card.querySelector('[data-runtime-status]');
        statusNode.textContent = status;
        statusNode.classList.toggle('bad', runtime.status !== 'Online' || runtime.version_status === 'Version mismatch');
        put('[data-version-status]', runtime.version_status);
        put('[data-php-version]', `${runtime.expected_version} / ${info.php_version || '—'}`);
        put('[data-sapi]', info.sapi || '—');
        put('[data-apache]', info.apache_version || '—');
        put('[data-runtime-terminal]', info.terminal_status || '—');
        put('[data-zend]', info.zend_version || '—');
        put('[data-build]', info.build ? `${info.build.os_family} · ${info.build.architecture} · ${info.build.zts ? 'ZTS' : 'NTS'}` : '—');
        put('[data-started-at]', newStart || '—');
        put('[data-configuration]', info.configuration ? Object.entries(info.configuration).map(([name, value]) => `${name}=${value}`).join('; ') : '—');
        put('[data-extensions]', info.loaded_extensions ? info.loaded_extensions.join(', ') : '—');
    };

    const refreshStatuses = async () => {
        if (!statusUrl) return;
        try {
            const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const runtimes = await response.json();
            Object.entries(runtimes).forEach(([key, runtime]) => renderRuntime(key, runtime));
        } catch (_) {
            document.querySelectorAll('[data-runtime-status]').forEach((node) => {
                node.textContent = 'Health check unavailable';
                node.classList.add('bad');
            });
        }
    };

    const checkTerminal = async (panel) => {
        if (!panel || !terminalStatus) return;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 2500);
        try {
            await fetch(panel.src, { mode: 'no-cors', cache: 'no-store', signal: controller.signal });
            terminalStatus.textContent = `${panel.dataset.terminalFrame} terminal reachable`;
            terminalStatus.classList.remove('bad');
        } catch (_) {
            terminalStatus.textContent = `${panel.dataset.terminalFrame} terminal unreachable`;
            terminalStatus.classList.add('bad');
        } finally {
            window.clearTimeout(timeout);
        }
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => {
        const selected = tab.dataset.selectTerminal;
        tabs.forEach((item) => {
            const active = item === tab;
            item.setAttribute('aria-pressed', String(active));
            item.setAttribute('aria-selected', String(active));
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.terminalFrame !== selected; });
        checkTerminal(panels.find((panel) => panel.dataset.terminalFrame === selected));
    }));

    document.querySelector('[data-clear-terminal]')?.addEventListener('click', () => {
        const active = panels.find((panel) => !panel.hidden);
        if (active) active.src = active.src;
        checkTerminal(active);
        statusLine.textContent = 'Terminal reconnected. Bash history remains in the container’s temporary filesystem.';
    });

    document.querySelectorAll('[data-copy-reset]').forEach((button) => button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyReset);
            statusLine.textContent = `Copied: ${button.dataset.copyReset}. Paste it into a Kali terminal to reset that container.`;
        } catch (_) {
            statusLine.textContent = `Run this in a Kali terminal: ${button.dataset.copyReset}`;
        }
    }));

    refreshStatuses();
    checkTerminal(panels.find((panel) => !panel.hidden));
    window.setInterval(refreshStatuses, 10000);
})();
</script>
</body>
</html>
