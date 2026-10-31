#!/bin/sh
set -eu

date -u +%Y-%m-%dT%H:%M:%S.%NZ > /tmp/php2shell-started-at

/usr/local/bin/apache2-foreground &
apache_pid=$!

export PS1="www-data@PHP${LAB_RUNTIME_EXPECTED_VERSION}:\\w\\$ "
ttyd --check-origin --writable --max-clients 1 --port 7681 --cwd /var/www/html /bin/bash --noprofile --norc -i &
ttyd_pid=$!

terminate() {
    kill -TERM "$apache_pid" "$ttyd_pid" 2>/dev/null || true
    wait "$apache_pid" 2>/dev/null || true
    wait "$ttyd_pid" 2>/dev/null || true
}

trap terminate INT TERM

while kill -0 "$apache_pid" 2>/dev/null && kill -0 "$ttyd_pid" 2>/dev/null; do
    sleep 1
done

terminate
exit 1
