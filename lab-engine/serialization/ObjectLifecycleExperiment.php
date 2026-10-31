<?php

declare(strict_types=1);

namespace Php2ShellLab\Serialization;

use Php2ShellLab\Fixtures\TrustedFixtureFactory;
use RuntimeException;

final class ObjectLifecycleExperiment
{
    public function run(): array
    {
        BenignLifecycleProbe::resetEvents();

        $fixture = TrustedFixtureFactory::serializedLifecycleObject();
        $object = unserialize($fixture, [
            'allowed_classes' => [BenignLifecycleProbe::class],
        ]);

        if (! $object instanceof BenignLifecycleProbe) {
            throw new RuntimeException('The trusted fixture did not restore the expected benign class.');
        }

        $restoredObject = [
            'class' => $object::class,
            'label' => $object->label,
        ];

        unset($object);

        return [
            'fixture' => $fixture,
            'restored_object' => $restoredObject,
            'events' => BenignLifecycleProbe::events(),
        ];
    }
}
