# PHP2Shell CTF Lab

A local PHP2Shell learning lab that compares PHP 8.5.11 with patched PHP 8.5.12. The CTF flag is gated by an Ed25519-signed attestation from a separately supplied, independently reviewed verifier. This repository does **not** implement a PHP2Shell exploit or trigger. Laravel validates who signed an attestation, its freshness, challenge, and target; it does not independently prove the verifier's RCE claim.

Regression references and the CVE-2026-109619 lifecycle material are supporting education only. Neither is presented as successful RCE evidence.

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
   sudo docker compose up --build -d
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

To stop the lab and remove its containers and networks while preserving the SQLite audit volume:

```sh
docker compose down --remove-orphans
```

The Laravel page polls runtime health every 10 seconds. Run tests in the isolated test profile with:

```sh
docker compose --profile test run --build --rm php2shell-tests
```

## CTF and trusted verifier integration

1. Configure `PHP2SHELL_VERIFIER_PUBLIC_KEY` in `.env` with the base64-encoded raw Ed25519 public key from your independently reviewed verifier. Keep its private key in the verifier's secret store. The public key is not sent to browser pages or PHP runtime containers.
2. Start the lab and open <http://127.0.0.1:8080>. Select **Start challenge** and copy the displayed challenge ID.
3. Provide that ID to your separately supplied trusted verifier. This repository contains no exploit, trigger, or verifier procedure. Until a verifier is configured and sends a valid signed result, the page will not reveal the flag.
4. The verifier posts the signed attestation to `http://php2shell-lab:8000/internal/ctf/attestations` from a container attached to the internal `php2shell-verification` Docker network. The endpoint is also reachable on the host only through the app's loopback binding at `http://127.0.0.1:8080/internal/ctf/attestations`.
5. After Laravel accepts an attestation for the active challenge and PHP 8.5.11 target, the browser session that started the round sees the flag through the status endpoint. The attestation POST response never contains the flag.

### Attestation contract

The request body is the complete attestation as RFC 8785 JSON Canonicalization Scheme bytes: exactly these ASCII string fields, with canonical property ordering and no extra whitespace or fields.

```json
{"challenge_id":"<active-challenge-uuid>","event_id":"<unique-event-uuid>","evidence_reference":"<opaque-reference>","expires_at":"2026-10-31T12:05:00Z","issued_at":"2026-10-31T12:00:00Z","target_id":"php2shell-vulnerable","target_php_version":"8.5.11","verification_status":"verified"}
```

Send the detached Ed25519 signature as unpadded base64url in `X-CTF-Signature`. Sign the exact canonical request body. Timestamps are UTC seconds ending in `Z`; an attestation must be current, no more than five minutes old, and have a lifetime of at most five minutes. The only successful target tuple is `php2shell-vulnerable` / `8.5.11`. The evidence reference is an opaque token, not a URL; Laravel never fetches it. Signed `not_verified` results and all malformed, expired, unauthenticated, duplicate, replayed, or incorrectly targeted events are rejected and audited.

The timestamp values above are illustrative only. A verifier must generate fresh `issued_at` and `expires_at` values for every attestation.

Laravel trusts the public key configured for the verifier. A valid signature proves the event came from whoever controls the matching private key; it does not make Laravel independently validate the evidence or the verifier's procedure. Never put the verifier private key in this repository, `.env`, Laravel, either PHP runtime, or a browser.

### Generate verifier keys

Generate a key pair on a trusted machine using PHP's sodium extension. Store the private key only in the verifier's secret manager and configure only the public key in Laravel's `.env`:

```sh
php -r '$pair = sodium_crypto_sign_keypair(); echo "PUBLIC=".base64_encode(sodium_crypto_sign_publickey($pair)).PHP_EOL; echo "PRIVATE=".base64_encode(sodium_crypto_sign_secretkey($pair)).PHP_EOL;'
```

Do not paste the private key into chat, commit it, or pass it as a Docker build argument. After setting the public key, restart the Laravel service:

```sh
docker compose up -d --force-recreate php2shell-lab
```

An independently supplied verifier can be attached to the internal network by its Compose configuration or with `docker network connect php2shell-verification <verifier-container>`. It should POST only to the Laravel service name on port 8000. The network is internal and has no host-published port.

### Supporting learning material

- **CVE-2026-109618:** the page links to PHP's [`Zend/tests/oom_request_parsing.phpt`](https://github.com/php/php-src/blob/php-8.5.12/Zend/tests/oom_request_parsing.phpt) as an upstream regression reference. This Laravel app does not execute that test, and its result is not an RCE attestation.
- **CVE-2026-109619:** the page explains the request-owned state lifecycle: state is published, cleanup is interrupted, request memory is freed while worker state remains, and a later request may consume stale state before normal initialization. This is conceptual material, not a runtime test.
- The app retains the PHP 8.5.11 and PHP 8.5.12 runtime comparison and its existing command, session, and serialization exercises. Those modules do not establish PHP2Shell RCE.

Starting a new challenge supersedes the previous active challenge for that browser session. Audit and verifier event records persist in the `ctf-state` Docker volume across container restarts. To reset all local challenge and audit state, including the SQLite volume, run:

```sh
docker compose down --volumes --remove-orphans
docker compose up --build -d
```

The flag is configured only in Laravel. It is not copied into the vulnerable runtime or its Bash terminal. A local Docker administrator can inspect or alter the Laravel container and database; this lab does not provide tamper-proof competition scoring.

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
- Each PHP runtime container stays on its own internal Docker network and has no external network route. Laravel and the Nginx terminal proxy join the frontend bridge network so Docker can publish their localhost-only ports. Laravel also joins the internal `php2shell-verification` network; a separately supplied verifier can join that network to reach Laravel, but the network has no external route or published port.
- No container is privileged. No Docker socket or Kali host directory is mounted.
- PHP terminals run as `www-data`; the containers use read-only root filesystems, temporary writable paths, dropped capabilities, `no-new-privileges`, 64-process limits, 256 MiB memory limits, and one CPU each.
- Laravel stores challenges, accepted attestations, and verification audit records in SQLite on the named `ctf-state` volume. The PHP runtime containers receive neither the flag nor the verifier private key.
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
