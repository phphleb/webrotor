<?php

declare(strict_types=1);

namespace Phphleb\Webrotor\Src\Process;

use Phphleb\Webrotor\Src\Exception\WebRotorException;
use Phphleb\Webrotor\Src\InternalConfig;
use Phphleb\Webrotor\Src\Storage\StorageInterface;

/**
 * @author Foma Tuturov <fomiash@yandex.ru>
 */
class ResponseSearchEngine
{
    /**
     * @var string
     */
    private $tag;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var InternalConfig
     */
    private $config;

    public function __construct(
        StorageInterface $storage,
        InternalConfig   $config,
        string           $tag
    )
    {
        $this->tag = $tag;
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * Searches for a matching request and returns
     * an array of data or null if timed out.
     *
     * @return null|array{
     *          statusCode: int,
     *          body: string,
     *          headers: string[][],
     *          reasonPhrase: string,
     *          version: string,
     *          middleware: array{
     *             session: array{
     *              sessionId: string,
     *              sessionName: string,
     *              session: array<string, mixed>
     *             },
     *             cookie: array<string, array{
     *              name: string,
     *              value: string,
     *              expires: int,
     *              path: string,
     *              domain: string,
     *              secure: bool,
     *              httponly: bool,
     *            }>
     *           }
     *          }
     */
    public function search(): ?array
    {
        $start = $this->config->getStartUnixTime();
        $max = $this->config->getWorkerResponseTimeSec();
        $tag = $this->tag;

        while (true) {
            if (!$max) {
                return null;
            }
            $mtime = microtime(true);
            if ($mtime >= $start + $max) {
                // If the worker didn’t take up processing, then there’s no need.
                $this->storage->delete($tag, Worker::REQUEST_TYPE);
                return null;
            }
            $response = $this->storage->get($tag, Worker::RESPONSE_TYPE);
            if ($response === null) {
                continue;
            }
            if (!$response) {
                throw new WebRotorException("(S) The received server response {$tag} is empty");
            }
            $this->storage->delete($tag, Worker::REQUEST_TYPE);
            if (!$this->config->isDebug()) {
                $this->storage->delete($tag, Worker::RESPONSE_TYPE);
            }
            /**
             * @var array{
             *        statusCode: int,
             *        body: string,
             *        headers: string[][],
             *        reasonPhrase: string,
             *        version: string,
             *        middleware: array{
             *         session: array{
             *            sessionId: string,
             *            sessionName: string,
             *            session: array<string, mixed>
             *          },
             *         cookie: array<string, array{
             *           name: string,
             *           value: string,
             *           expires: int,
             *           path: string,
             *           domain: string,
             *           secure: bool,
             *           httponly: bool,
             *         }>
             *      }
             *   } $data
             */
            $data = (array)json_decode($response, true);

            return $data;
        }
    }
}
