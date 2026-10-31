<?php

declare(strict_types=1);

namespace Php2ShellLab\Session;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class SessionExperiment
{
    private const MAX_WORKER_OUTPUT_BYTES = 16_384;

    public function run(string $format, string $sample): array
    {
        if (! in_array($format, ['php', 'php_serialize'], true)) {
            throw new InvalidArgumentException('Unsupported PHP session serialization format.');
        }

        if (strlen($sample) > 1024) {
            throw new InvalidArgumentException('The sample input is too large.');
        }

        $storagePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'php2shell-session-'.bin2hex(random_bytes(12));

        if (! mkdir($storagePath, 0700)) {
            throw new RuntimeException('Could not create temporary session storage.');
        }

        try {
            $process = new Process([
                PHP_BINARY,
                __DIR__.DIRECTORY_SEPARATOR.'worker.php',
            ], base_path());
            $process->setTimeout(3);
            $process->disableOutput();
            $process->setInput(json_encode([
                'format' => $format,
                'sample' => $sample,
                'storage_path' => $storagePath,
            ], JSON_THROW_ON_ERROR));

            $stdout = '';
            $stderr = '';
            $truncated = false;

            $exitCode = $process->run(function (string $type, string $chunk) use ($process, &$stdout, &$stderr, &$truncated): void {
                $remaining = self::MAX_WORKER_OUTPUT_BYTES - strlen($stdout) - strlen($stderr);
                $target = $type === Process::ERR ? $stderr : $stdout;

                if ($remaining > 0) {
                    $target .= substr($chunk, 0, $remaining);
                }

                if ($type === Process::ERR) {
                    $stderr = $target;
                } else {
                    $stdout = $target;
                }

                if (strlen($chunk) > $remaining) {
                    $truncated = true;
                    $process->stop(0);
                }
            });

            if ($truncated || $exitCode !== 0) {
                throw new RuntimeException('The isolated session worker did not complete successfully.');
            }

            try {
                $result = json_decode($stdout, true, 16, JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                throw new RuntimeException('The isolated session worker returned invalid data.', previous: $exception);
            }

            if (! is_array($result) || isset($result['error'])) {
                throw new RuntimeException('The isolated session worker could not complete this experiment.');
            }

            return $result;
        } finally {
            foreach (glob($storagePath.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($storagePath);
        }
    }
}
