<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PrepareCommand;
use App\Helper\CurrentContext;
use App\Middleware\Binary\Docker;
use App\Tests\CustomProphecyTrait;
use App\Tests\TestCommandTrait;
use App\Tests\TestLocationTrait;
use Prophecy\Argument;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 *
 * @covers \App\Command\AbstractBaseCommand
 * @covers \App\Command\PrepareCommand
 *
 * @uses \App\Event\AbstractEnvironmentEvent
 */
final class PrepareCommandTest extends WebTestCase
{
    use CustomProphecyTrait;
    use TestCommandTrait;
    use TestLocationTrait;

    public function testItPreparesTheActiveEnvironment(): void
    {
        $environment = $this->createEnvironment();
        [$currentContext, $docker] = $this->prophesizeObjectArguments();

        $currentContext->loadEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce();
        $currentContext->getActiveEnvironment()->shouldBeCalledOnce()->willReturn($environment);
        $docker->pullServices()->shouldBeCalledOnce()->willReturn(true);
        $docker->buildServices()->shouldBeCalledOnce()->willReturn(true);

        $command = new PrepareCommand($currentContext->reveal(), $docker->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $display = $commandTester->getDisplay();

        static::assertDisplayIsVerbose($environment, $display);
        static::assertStringContainsString('[OK] ', $display);
        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testItGracefullyExitsWhenAnExceptionOccurred(): void
    {
        $environment = $this->createEnvironment();
        [$currentContext, $docker] = $this->prophesizeObjectArguments();

        $currentContext->loadEnvironment(Argument::type(InputInterface::class))->shouldBeCalledOnce();
        $currentContext->getActiveEnvironment()->shouldBeCalledOnce()->willReturn($environment);
        $docker->pullServices()->shouldBeCalledOnce()->willReturn(true);
        $docker->buildServices()->shouldBeCalledOnce()->willReturn(false);

        $command = new PrepareCommand($currentContext->reveal(), $docker->reveal());
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('[ERROR] ', $display);
        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * {@inheritdoc}
     */
    protected function prophesizeObjectArguments(): array
    {
        return [
            $this->prophesize(CurrentContext::class),
            $this->prophesize(Docker::class),
        ];
    }
}
