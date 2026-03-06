<?php

declare(strict_types=1);

namespace LogTide\Integration;

use LogTide\Event;
use LogTide\State\Scope;

final class RequestIntegration implements IntegrationInterface
{
    public function getName(): string
    {
        return 'request';
    }

    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event): Event {
            if (PHP_SAPI === 'cli') {
                return $event;
            }

            $request = [];

            if (isset($_SERVER['REQUEST_METHOD'])) {
                $request['method'] = $_SERVER['REQUEST_METHOD'];
            }
            if (isset($_SERVER['REQUEST_URI'])) {
                $request['url'] = $_SERVER['REQUEST_URI'];
            }
            if (isset($_SERVER['HTTP_HOST'])) {
                $request['host'] = $_SERVER['HTTP_HOST'];
            }
            if (isset($_SERVER['QUERY_STRING'])) {
                $request['query_string'] = $_SERVER['QUERY_STRING'];
            }
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $request['ip'] = $_SERVER['REMOTE_ADDR'];
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                $request['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            }

            if (!empty($request)) {
                $event->addMetadata('request', $request);
            }

            return $event;
        });
    }

    public function teardown(): void
    {
    }
}
