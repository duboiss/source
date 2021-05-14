<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\StopCommand;
use App\Helper\CurrentContext;
use App\Middleware\Binary\Docker;
use App\Tests\CustomProphecyTrait;
use App\Tests\TestCommandTrait;
use App\Tests\TestEnvironmentTrait;
use Prophecy\Argument;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \App\Command\AbstractBaseCommand
 * @covers \App\Command\StopCommand
 *
 * @uses \App\Event\AbstractEnvironmentEvent::__construct()
 */
final class StopCommandTest extends WebTestCase
{
    use CustomProphecyTrait;
    use TestCommandTrait;
    use TestEnvironmentTrait;

    public function testItExecutesProcessSuccessfully(): void
    {
        $environment = $this->createEnvironment();
        [$currentContext, $docker, $eventDispatcher] = $this->prophesizeObjectArguments();

        $currentContext->loadEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce();
        $currentContext->getActiveEnvironment()->shouldBeCalledOnce()->willReturn($environment);
        $docker->stopServices()->shouldBeCalledOnce()->willReturn(true);
        $eventDispatcher->dispatch(Argument::any())->willReturn(new stdClass());

        $command = new StopCommand($currentContext->reveal(), $docker->reveal(), $eventDispatcher->reveal());
        static::assertResultIsSuccessful($command, $environment);
    }

    public function testItGracefullyExitsWhenAnExceptionOccurred(): void
    {
        $environment = $this->createEnvironment();
        [$currentContext, $docker, $eventDispatcher] = $this->prophesizeObjectArguments();

        $currentContext->loadEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce();
        $currentContext->getActiveEnvironment()->shouldBeCalledOnce()->willReturn($environment);
        $docker->stopServices()->shouldBeCalledOnce()->willReturn(false);

        $command = new StopCommand($currentContext->reveal(), $docker->reveal(), $eventDispatcher->reveal());
        static::assertExceptionIsHandled($command);
    }

    /**
     * {@inheritdoc}
     */
    protected function prophesizeObjectArguments(): array
    {
        return [
            $this->prophesize(CurrentContext::class),
            $this->prophesize(Docker::class),
            $this->prophesize(EventDispatcherInterface::class),
        ];
    }
}
