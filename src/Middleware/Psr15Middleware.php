<?php

declare(strict_types=1);

namespace LogTide\Middleware;

use LogTide\Breadcrumb\Breadcrumb;
use LogTide\Enum\BreadcrumbType;
use LogTide\Enum\LogLevel;
use LogTide\Enum\SpanKind;
use LogTide\Enum\SpanStatus;
use LogTide\LogtideSdk;
use LogTide\Tracing\PropagationContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Psr15Middleware implements MiddlewareInterface
{
    /** @var string[] */
    private readonly array $skipPaths;

    public function __construct(
        array $skipPaths = ['/health', '/healthz'],
    ) {
        $this->skipPaths = $skipPaths;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        foreach ($this->skipPaths as $skip) {
            if (str_starts_with($path, $skip)) {
                return $handler->handle($request);
            }
        }

        $hub = LogtideSdk::getCurrentHub();

        return $hub->withScope(function () use ($request, $handler, $hub) {
            $scope = $hub->getScope();

            $traceparent = $request->getHeaderLine('traceparent');
            if (!empty($traceparent)) {
                $context = PropagationContext::fromTraceparent($traceparent);
                if ($context !== null) {
                    $scope->setPropagationContext($context);
                }
            }

            $method = $request->getMethod();
            $path = $request->getUri()->getPath();
            $startTime = microtime(true);

            $span = $hub->startSpan("HTTP {$method} {$path}", [
                'kind' => SpanKind::SERVER,
            ]);

            $span?->setAttributes([
                'http.method' => $method,
                'http.url' => (string) $request->getUri(),
                'http.target' => $path,
            ]);

            $scope->addBreadcrumb(new Breadcrumb(
                BreadcrumbType::HTTP,
                "{$method} {$path}",
                category: 'http.request',
            ));

            try {
                $response = $handler->handle($request);

                $statusCode = $response->getStatusCode();
                $duration = (microtime(true) - $startTime) * 1000;

                $span?->setAttribute('http.status_code', $statusCode);

                if ($statusCode >= 500) {
                    $span?->setStatus(SpanStatus::ERROR);
                    $hub->captureLog(LogLevel::ERROR, "HTTP {$statusCode} {$method} {$path}", [
                        'http.status_code' => $statusCode,
                        'http.duration_ms' => round($duration, 2),
                    ]);
                } else {
                    $span?->setStatus(SpanStatus::OK);
                }

                if ($span !== null) {
                    $hub->finishSpan($span);
                }

                $traceparentHeader = $scope->getPropagationContext()->toTraceparent();
                return $response->withHeader('traceparent', $traceparentHeader);
            } catch (\Throwable $e) {
                $span?->setStatus(SpanStatus::ERROR, $e->getMessage());
                if ($span !== null) {
                    $hub->finishSpan($span);
                }

                $hub->captureException($e);
                throw $e;
            }
        });
    }
}
