# PHP2Shell Lab

PHP2Shell Lab is a small Laravel learning environment for PHP command injection, PHP session serialization, and object deserialization. The vulnerable command-injection path is simulated. Session and object experiments use controlled, trusted fixtures inside the dedicated Docker container.

No database, login system, or public service is required.

## Start on Kali Linux

Requirements: Docker Engine and the Docker Compose plugin.

1. From the project directory, ensure `.env` exists. If needed, copy `.env.example` to `.env`.
2. Generate an application key if `.env` does not already have one:

   ```sh
   php artisan key:generate
   ```

3. Build and start the lab:

   ```sh
   docker compose up --build
   ```

4. Open <http://127.0.0.1:8080>. Stop the app with `Ctrl+C` or `docker compose down`.

The app publishes only on `127.0.0.1`. Its Docker network is internal, the container has no host directory mounts, runs as `www-data`, drops Linux capabilities, uses a read-only root filesystem, and receives only small temporary writable filesystems. The container has no external network route. The test service runs separately with `network_mode: none`.

Run automated tests inside the isolated test container:

```sh
docker compose --profile test run --build --rm php2shell-tests
```

The test image includes development dependencies. Native session integration tests are skipped when run outside the marked Docker image.

## Laboratory modules

- **Command-injection simulator:** Try `hello; id` in the vulnerable-pattern mode. The app identifies shell syntax but does not execute it. Secure mode rejects characters outside its allowlist.
- **PHP Execution Environment:** Runs only the fixed `whoami`, `id`, or `uname` executable through Symfony Process. There is no user command field, shell parsing, arbitrary arguments, or free-form execution. Each process has a 2-second timeout and a 4 KiB output cap.
- **Session Configuration & Inspector:** Starts a separate native PHP process, independent of Laravel middleware. It displays PHP and session settings, writes a temporary session, shows its raw storage representation, and retrieves the same values.
- **Serialization Tester:** Wraps submitted text in a fixed PHP array and shows `serialize()` output. Input that resembles PHP or serialized notation remains a string; the submitted text is never evaluated or passed to `unserialize()`.
- **Object Deserialization:** Generates a trusted fixture for one benign class and restores it with an explicit class allowlist. Its `__construct()`, `__wakeup()`, and `__destruct()` methods only record events in the Laravel log. No HTTP input is deserialized.
- **Exploitation Flow & Security Analysis:** Describes the theoretical relationship between session handling, serialization parsing, object lifecycle methods, and potential code execution. It is not a demonstrated RCE.

## Session serialization notes

The `php` session serializer stores each session key followed by `|` and that value's PHP serialization. The `php_serialize` handler serializes the complete session array. Both formats are compared by the isolated worker using a fresh temporary directory. Special characters in the sample are values inside a normal string; they are not treated as PHP code or as new session keys. Temporary session files are deleted after each request.

The worker reports its PHP version, file save handler, selected serializer, temporary storage path, cookie name, strict-mode setting, and whether cookies are enabled. Cookies are disabled in the worker because it runs as a one-shot CLI process.

## Safety boundaries

- Session experiments and object lifecycle demos require both the dedicated image marker and explicit Docker configuration flags. They are disabled in a normal local Laravel server.
- The session worker accepts only `php` or `php_serialize` and a bounded string value. It receives no raw session file, class name, PHP code, or shell command from the request.
- Deserialization is limited to an app-generated fixture and the single benign `BenignLifecycleProbe` class. Its magic methods have no operating-system, filesystem, or network behavior beyond writing lifecycle records to the app log.
- The vulnerable `shell_exec()` example is illustrative only. The app never concatenates request data into shell text.
- The Docker host port is intentionally available to the local host at `127.0.0.1`; the container has no host filesystem mounts and no external network access.

## Remediation guidance

Prefer data-only formats such as JSON for untrusted input. Avoid deserializing attacker-controlled PHP data. If PHP deserialization is unavoidable, authenticate the data before parsing, pass a strict `allowed_classes` list, review all magic methods and dependency classes, and keep PHP and libraries patched. Avoid shell execution; if a process is necessary, use a fixed executable and separately supplied arguments, narrow input validation, timeouts, output limits, and least privilege.

## Project layout

```text
app/Http/Controllers/LabController.php
lab-engine/session/worker.php
lab-engine/session/SessionExperiment.php
lab-engine/serialization/BenignLifecycleProbe.php
lab-engine/serialization/ObjectLifecycleExperiment.php
lab-engine/fixtures/TrustedFixtureFactory.php
resources/views/lab.blade.php
routes/web.php
tests/
Dockerfile
docker-compose.yml
README.md
```
