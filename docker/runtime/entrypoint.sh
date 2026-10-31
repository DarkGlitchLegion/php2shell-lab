#!/bin/bash
set -euo pipefail

date -u +%Y-%m-%dT%H:%M:%S.%NZ > /tmp/php2shell-started-at

if [[ "${LAB_RUNTIME_EXPECTED_VERSION:-}" == "8.5.11" && -n "${PHP2SHELL_CTF_FLAG:-}" ]]; then
    printf '%s' "$PHP2SHELL_CTF_FLAG" > /tmp/php2shell-ctf-flag
    chmod 0444 /tmp/php2shell-ctf-flag
fi

export PS1="www-data@PHP${LAB_RUNTIME_EXPECTED_VERSION}:\\w\\$ "
ttyd --debug 7 --check-origin --writable --max-clients 1 --port 7681 --cwd /var/www/html -- /bin/bash --noprofile --norc -i &
ttyd_pid=$!

attempt=0
until php -r '$socket = @fsockopen("127.0.0.1", 7681, $errno, $error, 0.25); $connected = is_resource($socket); if ($connected) { fclose($socket); } exit($connected ? 0 : 1);'; do
    if ! kill -0 "$ttyd_pid" 2>/dev/null; then
        set +e
        wait "$ttyd_pid"
        ttyd_status=$?
        set -e
        echo "ttyd exited during startup with status ${ttyd_status}." >&2
        exit 1
    fi

    attempt=$((attempt + 1))
    if [[ "$attempt" -ge 20 ]]; then
        echo "ttyd did not listen on port 7681 within 5 seconds." >&2
        kill -TERM "$ttyd_pid" 2>/dev/null || true
        wait "$ttyd_pid" 2>/dev/null || true
        exit 1
    fi

    sleep 0.25
done

echo "ttyd is ready on port 7681; starting Apache for PHP ${LAB_RUNTIME_EXPECTED_VERSION}."
/usr/local/bin/apache2-foreground &
apache_pid=$!

set +e
wait -n "$apache_pid" "$ttyd_pid"
service_status=$?
set -e

echo "A required lab service exited with status ${service_status}; stopping its sibling." >&2
kill -TERM "$apache_pid" "$ttyd_pid" 2>/dev/null || true
wait "$apache_pid" 2>/dev/null || true
wait "$ttyd_pid" 2>/dev/null || true

for log_file in /var/log/apache2/error.log /var/log/apache2/php-error.log; do
    if [[ -s "$log_file" ]]; then
        echo "Last Apache/PHP errors from ${log_file}:" >&2
        tail -n 30 "$log_file" >&2 || true
    fi
done

exit 1
