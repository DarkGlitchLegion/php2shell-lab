<?php

declare(strict_types=1);

namespace Php2ShellLab\Fixtures;

use Php2ShellLab\Serialization\BenignLifecycleProbe;

final class TrustedFixtureFactory
{
    public static function serializedLifecycleObject(): string
    {
        $probe = new BenignLifecycleProbe('trusted-lifecycle-fixture');
        $fixture = serialize($probe);
        unset($probe);

        return $fixture;
    }
}
