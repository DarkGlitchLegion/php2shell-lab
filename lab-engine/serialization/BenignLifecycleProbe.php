<?php

declare(strict_types=1);

namespace Php2ShellLab\Serialization;

use Illuminate\Support\Facades\Log;

final class BenignLifecycleProbe
{
    public string $label;

    /** @var list<array{event: string, label: string}> */
    private static array $events = [];

    public function __construct(string $label)
    {
        $this->label = $label;
        self::record('__construct', $this->label);
    }

    public function __wakeup(): void
    {
        self::record('__wakeup', $this->label);
    }

    public function __destruct()
    {
        self::record('__destruct', $this->label);
    }

    public static function resetEvents(): void
    {
        self::$events = [];
    }

    /** @return list<array{event: string, label: string}> */
    public static function events(): array
    {
        return self::$events;
    }

    private static function record(string $event, string $label): void
    {
        $entry = [
            'event' => $event,
            'label' => $label,
        ];

        self::$events[] = $entry;

        Log::info('PHP2Shell benign object lifecycle event', $entry);
    }
}
