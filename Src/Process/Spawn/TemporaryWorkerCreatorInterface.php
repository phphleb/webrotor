<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process\Spawn;

/**
 * Allows you to expand the number of workers as the load increases.
 */
interface TemporaryWorkerCreatorInterface
{
    /**
     * Launching an additional worker.
     */
   public function createWorker(): void;
}
