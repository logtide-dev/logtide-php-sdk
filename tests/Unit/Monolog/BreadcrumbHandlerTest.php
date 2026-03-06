<?php

declare(strict_types=1);

namespace LogTide\Tests\Unit\Monolog;

use LogTide\Client;
use LogTide\LogtideSdk;
use LogTide\Monolog\BreadcrumbHandler;
use LogTide\Options;
use LogTide\State\Hub;
use LogTide\Transport\NullTransport;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

final class BreadcrumbHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        LogtideSdk::reset();
    }

    private function setupSdk(): Hub
    {
        $client = new Client(
            Options::fromArray(['default_integrations' => false]),
            new NullTransport(),
        );
        $hub = new Hub($client);
        LogtideSdk::setCurrentHub($hub);
        return $hub;
    }

    public function testAddsBreadcrumbForLogRecord(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('app', [new BreadcrumbHandler()]);
        $logger->info('user login');

        $breadcrumbs = $hub->getScope()->getBreadcrumbs()->getAll();
        $this->assertCount(1, $breadcrumbs);
        $this->assertSame('user login', $breadcrumbs[0]->message);
        $this->assertSame('console', $breadcrumbs[0]->type->value);
    }

    public function testBreadcrumbCategoryIsChannel(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('payments', [new BreadcrumbHandler()]);
        $logger->info('charge processed');

        $breadcrumbs = $hub->getScope()->getBreadcrumbs()->getAll();
        $this->assertSame('payments', $breadcrumbs[0]->category);
    }

    public function testBreadcrumbDataContainsContext(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('test', [new BreadcrumbHandler()]);
        $logger->info('action', ['user_id' => 99]);

        $breadcrumbs = $hub->getScope()->getBreadcrumbs()->getAll();
        $this->assertSame(['user_id' => 99], $breadcrumbs[0]->data);
    }

    public function testLevelMapping(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('test', [new BreadcrumbHandler()]);
        $logger->debug('d');
        $logger->info('i');
        $logger->warning('w');
        $logger->error('e');
        $logger->critical('c');

        $breadcrumbs = $hub->getScope()->getBreadcrumbs()->getAll();
        $this->assertSame('debug', $breadcrumbs[0]->level->value);
        $this->assertSame('info', $breadcrumbs[1]->level->value);
        $this->assertSame('warn', $breadcrumbs[2]->level->value);
        $this->assertSame('error', $breadcrumbs[3]->level->value);
        $this->assertSame('critical', $breadcrumbs[4]->level->value);
    }

    public function testRespectsMinLevel(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('test', [new BreadcrumbHandler(Level::Error)]);
        $logger->debug('skip');
        $logger->info('skip');
        $logger->warning('skip');
        $logger->error('include');

        $this->assertSame(1, $hub->getScope()->getBreadcrumbs()->count());
    }

    public function testMultipleBreadcrumbs(): void
    {
        $hub = $this->setupSdk();

        $logger = new Logger('test', [new BreadcrumbHandler()]);
        for ($i = 0; $i < 5; $i++) {
            $logger->info("msg-{$i}");
        }

        $this->assertSame(5, $hub->getScope()->getBreadcrumbs()->count());
    }
}
