<?php

declare(strict_types=1);

namespace App\Tests\Middleware\Binary;

use App\Environment\Configuration\AbstractConfiguration;
use App\Environment\EnvironmentEntity;
use App\Helper\ProcessFactory;
use App\Middleware\Binary\DockerCompose;
use App\Tests\TestLocationTrait;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @covers \App\Middleware\Binary\DockerCompose
 */
final class DockerComposeDefaultTest extends WebTestCase
{
    use ProphecyTrait;
    use TestDockerComposeTrait;
    use TestLocationTrait;

    public function testItDefinesTheActiveEnvironmentWithInternals(): void
    {
        $environment = $this->createEnvironment();

        $processFactory = $this->prophesize(ProcessFactory::class);

        $dockerCompose = new DockerCompose($processFactory->reveal());
        $dockerCompose->refreshEnvironmentVariables($environment);

        $variables = $dockerCompose->getRequiredVariables($environment);

        static::assertArrayHasKey('COMPOSE_FILE', $variables);
        static::assertSame($this->location.AbstractConfiguration::INSTALLATION_DIRECTORY.'docker-compose.yml', $variables['COMPOSE_FILE']);

        static::assertArrayHasKey('COMPOSE_PROJECT_NAME', $variables);
        static::assertSame('symfony_origami', $variables['COMPOSE_PROJECT_NAME']);

        static::assertArrayHasKey('DOCKER_PHP_IMAGE', $variables);
        static::assertFalse($variables['DOCKER_PHP_IMAGE']);

        static::assertArrayHasKey('PROJECT_LOCATION', $variables);
        static::assertSame($this->location, $variables['PROJECT_LOCATION']);
    }

    public function testItDefinesTheActiveEnvironmentWithExternals(): void
    {
        $environment = new EnvironmentEntity('bar', $this->location, EnvironmentEntity::TYPE_CUSTOM, null, true);

        $processFactory = $this->prophesize(ProcessFactory::class);

        $dockerCompose = new DockerCompose($processFactory->reveal());
        $dockerCompose->refreshEnvironmentVariables($environment);

        $variables = $dockerCompose->getRequiredVariables($environment);

        static::assertArrayHasKey('COMPOSE_FILE', $variables);
        static::assertSame("{$this->location}/docker-compose.yml", $variables['COMPOSE_FILE']);

        static::assertArrayHasKey('COMPOSE_PROJECT_NAME', $variables);
        static::assertSame('custom_bar', $variables['COMPOSE_PROJECT_NAME']);

        static::assertArrayNotHasKey('DOCKER_PHP_IMAGE', $variables);

        static::assertArrayHasKey('PROJECT_LOCATION', $variables);
        static::assertSame($this->location, $variables['PROJECT_LOCATION']);
    }

    public function testItPreparesTheEnvironmentServices(): void
    {
        $commands = [
            ['docker-compose', 'pull'],
            ['docker-compose', 'build', '--pull', '--parallel'],
        ];
        $environment = $this->createEnvironment();

        $processFactory = $this->prophesize(ProcessFactory::class);
        $process = $this->prophesize(Process::class);

        $process->isSuccessful()->shouldBeCalledTimes(2)->willReturn(true);
        $processFactory->runForegroundProcess($commands[0], Argument::type('array'))->shouldBeCalledOnce()->willReturn($process->reveal());
        $processFactory->runForegroundProcess($commands[1], Argument::type('array'))->shouldBeCalledOnce()->willReturn($process->reveal());

        $dockerCompose = new DockerCompose($processFactory->reveal());
        $dockerCompose->refreshEnvironmentVariables($environment);

        static::assertTrue($dockerCompose->prepareServices());
    }

    public function testItShowsResourcesUsage(): void
    {
        $command = 'docker-compose ps -q | xargs docker stats';
        $dockerCompose = $this->prepareForegroundFromShellCommand($command);

        static::assertTrue($dockerCompose->showResourcesUsage());
    }

    public function testItShowsServicesStatus(): void
    {
        $command = ['docker-compose', 'ps'];
        $dockerCompose = $this->prepareForegroundCommand($command);

        static::assertTrue($dockerCompose->showServicesStatus());
    }

    public function testItRestartsServicesStatus(): void
    {
        $command = ['docker-compose', 'restart'];
        $dockerCompose = $this->prepareForegroundCommand($command);

        static::assertTrue($dockerCompose->restartServices());
    }

    public function testItStartsServicesStatus(): void
    {
        $command = ['docker-compose', 'up', '--build', '--detach', '--remove-orphans'];
        $dockerCompose = $this->prepareForegroundCommand($command);

        static::assertTrue($dockerCompose->startServices());
    }

    public function testItStopsServicesStatus(): void
    {
        $command = ['docker-compose', 'stop'];
        $dockerCompose = $this->prepareForegroundCommand($command);

        static::assertTrue($dockerCompose->stopServices());
    }

    public function testItRemovesServicesStatus(): void
    {
        $command = ['docker-compose', 'down', '--rmi', 'local', '--volumes', '--remove-orphans'];
        $dockerCompose = $this->prepareForegroundCommand($command);

        static::assertTrue($dockerCompose->removeServices());
    }
}
