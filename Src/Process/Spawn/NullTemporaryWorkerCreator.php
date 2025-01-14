<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process\Spawn;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
final class NullTemporaryWorkerCreator implements TemporaryWorkerCreatorInterface
{
    /** @inheritDoc */
    public function createWorker(): void
    {
        // Nothing happens.
    }
}
