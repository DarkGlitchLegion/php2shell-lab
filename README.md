# PHP2Shell CTF Lab

A local solo CTF practice game for comparing PHP 8.5.11 with patched PHP 8.5.12 for CVE-2026-109618 and CVE-2026-109619. It includes vulnerable and patched HTTP targets, a timed flag submission flow, runtime details, and real Bash terminals inside the containers.

The lab does not bundle exploit payloads or automation. Its CTF target is the intentionally vulnerable PHP runtime; the game checks a submitted practice flag, not whether the CVE caused code execution. A PHP version match is exposure identification, not proof that a CVE was successfully exploited.

## Prerequisites and startup

Install Docker Engine and the Docker Compose plugin. Run these commands from the project directory. On Kali systems where Docker requires elevated privileges, prefix each Docker command with `sudo`.

1. Create the local environment file if needed:

   ```sh
   [ -f .env ] || cp .env.example .env
   ```

   Keep an existing `.env`. If `APP_KEY` is empty, generate one before starting the containers:

   ```sh
   php artisan key:generate
   ```

2. Build and start the lab in the background:

   ```sh
   docker compose up --build -d
   ```

   Compose pulls the exact PHP images `php:8.5.11-apache-bookworm` and `php:8.5.12-apache-bookworm`. Startup fails if Docker cannot pull either tag; it does not substitute another PHP version.

3. Check that the services are running:

   ```sh
   docker compose ps
   docker compose logs --tail=50 php2shell-lab terminal-proxy
   ```

4. Open the application and terminal links:

   - Lab UI: <http://127.0.0.1:8080>
   - Vulnerable HTTP target: <http://127.0.0.1:8082/runtime-info.php>
   - Patched HTTP control: <http://127.0.0.1:8083/runtime-info.php>
   - Vulnerable PHP 8.5.11 terminal: <http://127.0.0.1:7681>
   - Patched PHP 8.5.12 terminal: <http://127.0.0.1:7682>

   Keep the Compose services running while using the page. If a terminal is unreachable, check `docker compose ps` and `docker compose logs --tail=100 terminal-proxy php2shell-vulnerable php2shell-patched`.

To stop the lab and remove its containers and networks:

```sh
docker compose down --remove-orphans
```

The Laravel page polls runtime health every 10 seconds. Run tests in the isolated test profile with:

```sh
docker compose --profile test run --build --rm php2shell-tests
```

## Beginner CTF walkthrough

This is a **local solo practice game**, not a verified exploit contest. Follow these steps to explore both targets and complete the flag workflow:

1. Start the lab using the commands above, open <http://127.0.0.1:8080>, and select **Start round**.
2. Make ordinary HTTP requests to compare the target and patched control:

   ```sh
   curl -i http://127.0.0.1:8082/runtime-info.php
   curl -i http://127.0.0.1:8083/runtime-info.php
   ```

   The CTF target is PHP 8.5.11; the control is PHP 8.5.12. The page also displays the PHP version, SAPI, and runtime configuration.

3. Read the vulnerability overview and compare the two environments. These are PHP engine memory-safety flaws that can occur before normal application code runs. A version match identifies a pre-patch runtime; it does not prove an exploit succeeded.
4. To complete this local practice round, open the vulnerable terminal at <http://127.0.0.1:7681> and read the flag file:

   ```sh
   cat /tmp/php2shell-ctf-flag
   ```

   Paste the returned value into **Submit flag**. This terminal route intentionally bypasses the vulnerability challenge; it is **not** a PHP2Shell exploit. The patched terminal is at <http://127.0.0.1:7682>.

The default flag is **`FLAG{php2shell_local_practice}`**. If `PHP2SHELL_CTF_FLAG` is set in `.env`, that configured value is expected instead. The flag file is created outside Apache's document root when the vulnerable container starts.

This repository does not provide exploit payloads or a step-by-step procedure for triggering the real PHP memory-corruption/RCE flaws. The target is intentionally vulnerable and must remain bound to localhost, disposable, and used only in this lab. Because both terminals and local Docker access are available, the timer and browser-local best time are self-reported practice scores, not proof of exploitation or a multiplayer first-blood record.

To reset the vulnerable target and recreate its temporary flag file, run this in a Kali terminal:

```sh
docker compose restart php2shell-vulnerable
```

The game timer is separate from container reset; select **Start round** again after the target restarts. The local best time lives in browser `localStorage` and can be cleared with the browser's site data.

## Environments and terminal

The vulnerable runtime uses PHP 8.5.11 and Apache with mod_php. The patched runtime uses PHP 8.5.12 and the same Apache configuration. Their Apache ports are available only on their respective internal Docker networks. The UI receives runtime information through fixed, read-only HTTP health endpoints.

The terminal panel uses [ttyd](https://github.com/tsl0922/ttyd) 1.7.7 with its bundled xterm.js frontend. It launches Bash as the unprivileged `www-data` user inside the selected PHP container. A small unprivileged Nginx proxy forwards browser HTTP and WebSocket traffic to ttyd; the PHP runtime containers themselves have no host-published ports. Browser connections are published on host loopback only:

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

- Laravel binds to `127.0.0.1:8080`; Nginx terminal proxy ports bind to `127.0.0.1:7681` and `127.0.0.1:7682`. Apache and ttyd ports on the PHP runtime containers are not published to the host.
- Each PHP runtime container stays on its own internal Docker network and has no external network route. Laravel and the Nginx terminal proxy join the frontend bridge network so Docker can publish their localhost-only ports; these two containers can make outbound connections.
- No container is privileged. No Docker socket or Kali host directory is mounted.
- PHP terminals run as `www-data`; the containers use read-only root filesystems, temporary writable paths, dropped capabilities, `no-new-privileges`, 64-process limits, 256 MiB memory limits, and one CPU each.
- Each ttyd instance checks the WebSocket origin and allows one local client. Do not forward these ports through a proxy or bind them to a non-loopback address.
- Docker containers share the host kernel. Container isolation is not a guarantee against kernel vulnerabilities or an escape. Keep the intentionally vulnerable runtime local, disposable, and unused for real data.
- The CTF comparison keeps `session.upload_progress` enabled and uses the same PHP configuration in both images so the runtime version is the main comparison. PHP says configuration workarounds are not complete mitigations; updating to a fixed release is the safe remediation.
- A failed runtime health check means either Apache's runtime endpoint or ttyd's local listener is unavailable. A changed container start time after recovery signals a restart. Startup logs report a ttyd startup failure and include recent Apache/PHP errors when a required process exits.

## Existing educational modules

The earlier non-CVE exercises remain available in the collapsed “Existing safe PHP command, session, and serialization exercises” section. They demonstrate separate concepts and are not tests or reproductions of CVE-2026-109618 or CVE-2026-109619. Their existing safety limits remain in place.

## Vulnerability references

- [PHP security advisory and fixed releases](https://www.php.net/archive/2026.php#2026-10-21-5)
- [Wiz Research technical report](https://www.wiz.io/blog/php2shell-php-rce)
- [ttyd project and terminal options](https://github.com/tsl0922/ttyd)
