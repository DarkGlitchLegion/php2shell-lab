# PHP2Shell Lab

A small Laravel single-page lab for learning how unsafe shell-command construction can turn untrusted input into command injection, and how to avoid that risk.

## Safety model

The vulnerable path is a **simulation**. It detects shell metacharacters and explains their effect; it never sends request data to a shell. The secure path validates a narrow character allowlist and creates a greeting directly in PHP. Neither path calls `shell_exec()`, `exec()`, `system()`, or starts another process.

The vulnerable code shown in the page is illustrative only. Do not copy it into an application.

## Run with Docker Compose

Requirements: Docker Engine and the Compose plugin.

1. From this directory, ensure `.env` exists. If needed, copy `.env.example` to `.env`.
2. Generate an application key if `.env` does not already contain one:

   ```sh
   php artisan key:generate
   ```

3. Build and start the lab:

   ```sh
   docker compose up --build
   ```

4. Open <http://localhost:8080>. Stop it with `Ctrl+C` (or run `docker compose down`).

Compose publishes only on `127.0.0.1`, places the container on an internal Docker network, drops Linux capabilities, enables `no-new-privileges`, runs as `www-data`, and makes the container filesystem read-only except for small temporary filesystems used by Laravel. The application source is baked into the image and remains read-only at runtime. No host directories or devices are mounted.

For a local Laravel development server instead:

```sh
composer install
php artisan key:generate
php artisan serve --host=127.0.0.1
```

Then visit <http://127.0.0.1:8000>.

## How it works

- `routes/web.php` maps the single page and form submission to `LabController`.
- `app/Http/Controllers/LabController.php` validates the request length and selected mode, simulates unsafe shell parsing, and safely renders a PHP-generated response for allowlisted input.
- `resources/views/lab.blade.php` contains the responsive interface, terminal-style output, educational comparison, status, explanation, and remediation.
- `Dockerfile` installs production dependencies and starts Laravel as an unprivileged user.
- `docker-compose.yml` applies local-only networking and runtime isolation.

## Learning notes

Shell functions such as `shell_exec()`, `exec()`, and `system()` pass command text to the operating system. Concatenating request values into that text can allow shell metacharacters to change the command's meaning. Prefer native PHP functions. When process execution is truly needed, use a fixed executable with separated arguments, validate values for their intended meaning, and apply a timeout and least privilege. Escaping alone is not a substitute for a safe design.
