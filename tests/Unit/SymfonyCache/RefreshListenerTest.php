<?php

/*
 * This file is part of the FOSHttpCache package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCache\Tests\Unit\SymfonyCache;

use FOS\HttpCache\SymfonyCache\CacheEvent;
use FOS\HttpCache\SymfonyCache\CacheInvalidation;
use FOS\HttpCache\SymfonyCache\RefreshListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\HttpFoundation\Response;

class RefreshListenerTest extends TestCase
{
    /**
     * @var CacheInvalidation&MockObject
     */
    private $kernel;

    public function setUp(): void
    {
        $this->kernel = $this->createMock(CacheInvalidation::class);
    }

    public function testRefreshAllowed()
    {
        $request = Request::create('http://example.com/foo');
        $request->headers->addCacheControlDirective('no-cache');
        $response = new Response('Test response');
        $event = new CacheEvent($this->kernel, $request);

        $this->kernel->expects($this->once())
            ->method('fetch')
            ->with($request)
            ->willReturn($response)
        ;

        $refreshListener = new RefreshListener();
        $refreshListener->handleRefresh($event);

        $this->assertSame($response, $event->getResponse());
    }

    public function testRefreshForbiddenMatcher()
    {
        $this->kernel->expects($this->never())
            ->method('fetch')
        ;

        $matcher = new PathRequestMatcher('/forbidden');
        $refreshListener = new RefreshListener(['client_matcher' => $matcher]);
        $request = Request::create('http://example.com/foo');
        $request->headers->addCacheControlDirective('no-cache');
        $event = new CacheEvent($this->kernel, $request);

        $refreshListener->handleRefresh($event);

        $this->assertNull($event->getResponse());
    }

    public function testRefreshForbiddenIp()
    {
        $this->kernel->expects($this->never())
            ->method('fetch')
        ;

        $refreshListener = new RefreshListener(['client_ips' => '1.2.3.4']);
        $request = Request::create('http://example.com/foo');
        $request->headers->addCacheControlDirective('no-cache');
        $event = new CacheEvent($this->kernel, $request);

        $refreshListener->handleRefresh($event);
        $this->assertNull($event->getResponse());
    }

    /**
     * Configuring the method to something else should make this listener skip the request.
     */
    public function testUnsafe()
    {
        $this->kernel->expects($this->never())
            ->method('fetch')
        ;

        $refreshListener = new RefreshListener();
        $request = Request::create('http://example.com/foo', 'POST');
        $request->headers->addCacheControlDirective('no-cache');
        $event = new CacheEvent($this->kernel, $request);

        $refreshListener->handleRefresh($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * Refresh only happens if no-cache is sent.
     */
    public function testNoRefresh()
    {
        $this->kernel->expects($this->never())
            ->method('fetch')
        ;

        $refreshListener = new RefreshListener();
        $request = Request::create('http://example.com/foo');
        $event = new CacheEvent($this->kernel, $request);

        $refreshListener->handleRefresh($event);

        $this->assertNull($event->getResponse());
    }

    public function testInvalidConfiguration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        new RefreshListener(['stuff' => '1.2.3.4']);
    }
}
