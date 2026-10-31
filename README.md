# PHP2Shell Lab

A local Docker laboratory for comparing PHP 8.5.11 with PHP 8.5.12 for CVE-2026-109618 and CVE-2026-109619. The application reports runtime versions, build and configuration details, Apache/PHP SAPI information, extensions, health, and container start times. A real `ttyd` terminal runs Bash inside each PHP container.

The lab performs normal health requests only. It does not include exploit payloads, memory disclosure or corruption, or an RCE endpoint. A PHP version match is exposure identification, not proof that a CVE was successfully exploited.

## Prerequisites and startup

Use Docker Engine with the Compose plugin. From the project directory:

```sh
[ -f .env ] || cp .env.example .env
docker compose up --build
```

If `.env` already exists, keep it. If `APP_KEY` is empty, run `php artisan key:generate` once before startup. Compose uses the exact images `php:8.5.11-apache-bookworm` and `php:8.5.12-apache-bookworm`. It fails the build if those tags cannot be pulled; no substitute versions are configured.

Open <http://127.0.0.1:8080>. The Laravel page polls runtime health every 10 seconds. Run tests in the isolated test profile with:

```sh
docker compose --profile test run --build --rm php2shell-tests
```

## Environments and terminal

The vulnerable runtime uses PHP 8.5.11 and Apache with mod_php. The patched runtime uses PHP 8.5.12 and the same Apache configuration. Their Apache ports are available only on their respective internal Docker networks. The UI receives runtime information through fixed, read-only HTTP health endpoints.

The terminal panel uses [ttyd](https://github.com/tsl0922/ttyd) 1.7.7 with its bundled xterm.js frontend. It launches Bash as the unprivileged `www-data` user inside the selected PHP container. Browser connections are published on host loopback only:

- Vulnerable terminal: <http://127.0.0.1:7681>
- Patched terminal: <http://127.0.0.1:7682>

Use the terminal to inspect `php -v`, `php --ini`, `php -m`, `apache2 -v`, `ps -ef`, the working directory, and the temporary Apache/PHP logs under `/var/log/apache2`. Bash history and logs are stored only in temporary filesystems. Clearing/reconnecting the terminal creates a new shell while retaining history until the container restarts.

The UI's reset buttons copy a command for the Kali terminal. Paste the command there to restart one lab environment:

```sh
docker compose restart php2shell-vulnerable
docker compose restart php2shell-patched
```

Restarting replaces each container's `/tmp`, Apache runtime files, and logs with empty temporary filesystems. To remove the lab containers and networks:

```sh
docker compose down --remove-orphans
```

For a full image rebuild, remove the project images explicitly:

```sh
docker compose down --rmi local --remove-orphans
docker compose build --no-cache
```

## Isolation model and limitations

- Laravel binds to `127.0.0.1:8080`; terminal ports bind to `127.0.0.1:7681` and `127.0.0.1:7682`. Apache runtime ports are not published to the host.
- Each PHP container has its own internal Docker network. No service has an external network route.
- No container is privileged. No Docker socket or Kali host directory is mounted.
- PHP terminals run as `www-data`; the containers use read-only root filesystems, temporary writable paths, dropped capabilities, `no-new-privileges`, 64-process limits, 256 MiB memory limits, and one CPU each.
- Each ttyd instance checks the WebSocket origin and allows one local client. Do not forward these ports through a proxy or bind them to a non-loopback address.
- Docker containers share the host kernel. Container isolation is not a guarantee against kernel vulnerabilities or an escape. Keep the intentionally vulnerable runtime local, disposable, and unused for real data.
- The runtime configuration sets `post_max_size=8M` and disables `session.upload_progress`. These reduce specific attack paths described in the PHP advisory; PHP says they are not complete mitigations. The comparison identifies release versions and configuration, not exploitability in a particular deployment.
- A failed runtime health check means either Apache's runtime endpoint or ttyd's local listener is unavailable. A changed container start time after recovery signals a restart. Startup logs report a ttyd startup failure and include recent Apache/PHP errors when a required process exits.

## Existing educational modules

The earlier non-CVE exercises remain available in the collapsed “Existing safe PHP command, session, and serialization exercises” section. They demonstrate separate concepts and are not tests or reproductions of CVE-2026-109618 or CVE-2026-109619. Their existing safety limits remain in place.

## Vulnerability references

- [PHP security advisory and fixed releases](https://www.php.net/archive/2026.php#2026-10-21-5)
- [Wiz Research technical report](https://www.wiz.io/blog/php2shell-php-rce)
- [ttyd project and terminal options](https://github.com/tsl0922/ttyd)
