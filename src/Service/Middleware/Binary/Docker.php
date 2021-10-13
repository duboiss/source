<?php

declare(strict_types=1);

namespace App\Service\Middleware\Binary;

use App\Service\ApplicationContext;
use App\Service\Middleware\Database;
use App\Service\Wrapper\ProcessFactory;
use App\ValueObject\EnvironmentEntity;

class Docker
{
    private ApplicationContext $applicationContext;
    private ProcessFactory $processFactory;
    private string $installDir;

    public function __construct(ApplicationContext $applicationContext, ProcessFactory $processFactory, string $installDir)
    {
        $this->applicationContext = $applicationContext;
        $this->processFactory = $processFactory;
        $this->installDir = $installDir;
    }

    /**
     * Retrieves the version of the binary installed on the host.
     */
    public function getVersion(): string
    {
        return $this->processFactory->runBackgroundProcess(['docker', '--version'])->getOutput();
    }

    /**
     * Pulls the Docker images associated to the current environment.
     */
    public function pullServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['pull'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Builds the Docker images associated to the current environment.
     */
    public function buildServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['build', '--pull', '--parallel'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Shows the resources usage of the services associated to the current environment.
     */
    public function showResourcesUsage(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $defaultOptions = implode(' ', $this->getDefaultComposeOptions($environment));
        $command = "docker compose {$defaultOptions} ps --quiet | xargs docker stats";
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Shows the logs of the services associated to the current environment.
     */
    public function showServicesLogs(?int $tail = null, ?string $service = null): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['logs', '--follow', sprintf('--tail=%s', $tail ?? 0)];
        if ($service) {
            $action[] = $service;
        }
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Shows the status of the services associated to the current environment.
     */
    public function showServicesStatus(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['ps'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Restarts the services of the current environment.
     */
    public function restartServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['restart'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Starts the services after building the associated images.
     */
    public function startServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['up', '--build', '--detach', '--remove-orphans'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Stops the services of the current environment.
     */
    public function stopServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['stop'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Allow "www-data:www-data" to use the shared SSH agent.
     */
    public function fixPermissionsOnSharedSSHAgent(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['exec', '-T', 'php', 'bash', '-c', 'chown www-data:www-data /run/host-services/ssh-auth.sock'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Opens a terminal on the service associated to the command.
     */
    public function openTerminal(string $service, string $user = ''): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        // There is an issue when allocating a TTY with the "docker compose exec" instruction.
        $container = $environment->getType().'_'.$environment->getName()."-{$service}-1";

        $command = $user !== ''
            ? "docker exec -it --user={$user} {$container} bash --login"
            : "docker exec -it {$container} bash --login"
        ;
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Removes the services of the current environment.
     */
    public function removeServices(): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        $action = ['down', '--rmi', 'local', '--volumes', '--remove-orphans'];
        $command = array_merge(['docker', 'compose'], $this->getDefaultComposeOptions($environment), $action);
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcess($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Executes the native MySQL dump process.
     */
    public function dumpMysqlDatabase(string $path): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        // There is sometimes a "bad file descriptor" issue with the "docker compose exec" instruction.
        $container = $environment->getType().'_'.$environment->getName().'-database-1';

        $command = str_replace(
            ['{container}', '{password}', '{database}', '{filename}'],
            [$container, Database::DEFAULT_SERVICE_PASSWORD, Database::DEFAULT_SERVICE_DATABASE, $path],
            'docker exec --interactive {container} mysqldump --user=root --password={password} {database} > {filename}'
        );
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Executes the native Postgres dump process.
     */
    public function dumpPostgresDatabase(string $path): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        // There is sometimes a "bad file descriptor" issue with the "docker compose exec" instruction.
        $container = $environment->getType().'_'.$environment->getName().'-database-1';

        $command = str_replace(
            ['{container}', '{password}', '{database}', '{filename}'],
            [$container, Database::DEFAULT_SERVICE_PASSWORD, Database::DEFAULT_SERVICE_DATABASE, $path],
            'docker exec --interactive {container} pg_dump --clean --dbname=postgresql://postgres:{password}@127.0.0.1:5432/{database} > {filename}'
        );

        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Executes the native MySQL restore process.
     */
    public function restoreMysqlDatabase(string $path): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        // There is sometimes a "bad file descriptor" issue with the "docker compose exec" instruction.
        $container = $environment->getType().'_'.$environment->getName().'-database-1';

        $command = str_replace(
            ['{container}', '{password}', '{database}', '{filename}'],
            [$container, Database::DEFAULT_SERVICE_PASSWORD, Database::DEFAULT_SERVICE_DATABASE, $path],
            'docker exec --interactive {container} mysql --user=root --password={password} {database} < {filename}'
        );
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Executes the native Postgres restore process.
     */
    public function restorePostgresDatabase(string $path): bool
    {
        $environment = $this->applicationContext->getActiveEnvironment();

        // There is sometimes a "bad file descriptor" issue with the "docker compose exec" instruction.
        $container = $environment->getType().'_'.$environment->getName().'-database-1';

        $command = str_replace(
            ['{container}', '{password}', '{database}', '{filename}'],
            [$container, Database::DEFAULT_SERVICE_PASSWORD, Database::DEFAULT_SERVICE_DATABASE, $path],
            'docker exec --interactive {container} psql --dbname=postgresql://postgres:{password}@127.0.0.1:5432/{database} < {filename}'
        );
        $environmentVariables = $this->getEnvironmentVariables($environment);

        return $this->processFactory->runForegroundProcessFromShellCommandLine($command, $environmentVariables)->isSuccessful();
    }

    /**
     * Retrieves the options required by the "docker compose" commands when working in different directories.
     *
     * @return string[]
     */
    private function getDefaultComposeOptions(EnvironmentEntity $environment): array
    {
        $location = $environment->getLocation();

        return [
            '--file='.$location.$this->installDir.'/docker-compose.yml',
            '--project-directory='.$location,
            '--project-name='.$this->applicationContext->getProjectName(),
        ];
    }

    /**
     * Retrieves environment variables required to run processes.
     *
     * @return array<string, string>
     */
    private function getEnvironmentVariables(EnvironmentEntity $environment): array
    {
        return [
            'PROJECT_NAME' => $this->applicationContext->getProjectName(),
            'PROJECT_LOCATION' => $environment->getLocation(),
        ];
    }
}
