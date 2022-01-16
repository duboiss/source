<?php

declare(strict_types=1);

namespace App\Tests\Command\Database;

use App\Command\Database\RestoreCommand;
use App\Exception\InvalidConfigurationException;
use App\Service\ApplicationContext;
use App\Service\Middleware\Binary\Docker;
use App\Service\Middleware\Database;
use App\Tests\TestCommandTrait;
use App\Tests\TestEnvironmentTrait;
use Iterator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 *
 * @covers \App\Command\AbstractBaseCommand
 * @covers \App\Command\Database\RestoreCommand
 */
final class RestoreCommandTest extends TestCase
{
    use ProphecyTrait;
    use TestCommandTrait;
    use TestEnvironmentTrait;

    /**
     * @dataProvider provideDatabaseConfigurations
     */
    public function testItTriggersDatabaseRestore(string $type, string $username, string $password, string $method): void
    {
        $applicationContext = $this->prophesize(ApplicationContext::class);
        $database = $this->prophesize(Database::class);
        $docker = $this->prophesize(Docker::class);

        $environment = $this->createEnvironment();

        $backupFile = $this->location.'/'.Database::DEFAULT_BACKUP_FILENAME;
        touch($backupFile);

        $applicationContext
            ->loadEnvironment(Argument::type(InputInterface::class))
            ->shouldBeCalledOnce()
        ;

        $applicationContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $database
            ->getDatabaseType()
            ->shouldBeCalledOnce()
            ->willReturn($type)
        ;

        $database
            ->getDatabaseUsername()
            ->shouldBeCalledOnce()
            ->willReturn($username)
        ;

        $database
            ->getDatabasePassword()
            ->shouldBeCalledOnce()
            ->willReturn($password)
        ;

        $docker
            ->{$method}('username', 'password', Argument::type('string'))
            ->shouldBeCalledOnce()
            ->willReturn(true)
        ;

        $command = new RestoreCommand($applicationContext->reveal(), $database->reveal(), $docker->reveal());
        static::assertResultIsSuccessful($command, $environment);
    }

    public function testItGracefullyExitsWhenAnExceptionOccurred(): void
    {
        $applicationContext = $this->prophesize(ApplicationContext::class);
        $database = $this->prophesize(Database::class);
        $docker = $this->prophesize(Docker::class);

        $environment = $this->createEnvironment();

        $backupFile = $this->location.'/'.Database::DEFAULT_BACKUP_FILENAME;
        touch($backupFile);

        $applicationContext
            ->loadEnvironment(Argument::type(InputInterface::class))
            ->shouldBeCalledOnce()
        ;

        $applicationContext
            ->getActiveEnvironment()
            ->shouldBeCalledOnce()
            ->willReturn($environment)
        ;

        $database
            ->getDatabaseType()
            ->shouldBeCalledOnce()
            ->willThrow(InvalidConfigurationException::class)
        ;

        $command = new RestoreCommand($applicationContext->reveal(), $database->reveal(), $docker->reveal());
        static::assertExceptionIsHandled($command);
    }

    public function provideDatabaseConfigurations(): Iterator
    {
        yield 'mariadb' => ['mariadb', 'username', 'password', 'restoreMysqlDatabase'];
        yield 'mysql' => ['mysql', 'username', 'password', 'restoreMysqlDatabase'];
        yield 'postgres' => ['postgres', 'username', 'password', 'restorePostgresDatabase'];
    }
}
