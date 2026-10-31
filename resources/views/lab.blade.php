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
        pre { margin:10px 0 0; padding:13px; border:1px solid #27342c; border-radius:7px; background:#090e0c; color:#cdebd4; white-space:pre-wrap; overflow-wrap:anywhere }
        code { color:#b8f3c8; font:.9em ui-monospace,SFMono-Regular,monospace }
        footer { margin-top:26px; color:#7f8d84; font-size:.82rem; text-align:center }
        @media(max-width:620px) { main{padding-top:36px}.modes,.result-grid{grid-template-columns:1fr}.card-head,.card-body{padding:16px} }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">Local security learning environment</div>
    <h1>PHP2Shell <span>Lab</span></h1>
    <p class="intro">Explore how PHP applications can mishandle input passed to operating-system commands, then compare safer ways to process that input.</p>
    <div class="notice"><strong>Contained demonstration.</strong> This page never executes a shell command. The vulnerable path is simulated; the secure path validates input and responds in PHP. Run this lab locally.</div>

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
            <p class="muted" style="margin:16px 0 0">The function names <code>shell_exec()</code>, <code>exec()</code>, and <code>system()</code> all cross into operating-system command execution. The lab intentionally calls none of them.</p>
        </div>
    </section>
    <footer>PHP2Shell Lab · Local education only · No operating-system commands are executed</footer>
</main>
</body>
</html>
