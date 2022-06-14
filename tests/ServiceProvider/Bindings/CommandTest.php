<?php

namespace TwigBridge\Tests\ServiceProvider\Bindings;

use TwigBridge\Tests\Base;
use TwigBridge\ServiceProvider;

class CommandTest extends Base
{
    protected $app;

    public function setUp()
    {
        $this->app = $this->getApplication();

        $provider = new ServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    public function testTwigInstance()
    {
        $this->assertEquals(get_class($this->app['command.twig']), 'TwigBridge\Command\TwigBridge');
    }

    public function testCleanInstance()
    {
        $this->assertEquals(get_class($this->app['command.twig.clean']), 'TwigBridge\Command\Clean');
    }

    public function testLintInstance()
    {
        $this->assertEquals(get_class($this->app['command.twig.lint']), 'TwigBridge\Command\Lint');
    }
}
