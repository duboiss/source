<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace App\Event;

use App\Entity\Environment;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEnvironmentEvent extends Event
{
    /** @var Environment */
    protected $environment;

    /** @var SymfonyStyle */
    protected $symfonyStyle;

    /**
     * AbstractEnvironmentEvent constructor.
     */
    public function __construct(Environment $environment, SymfonyStyle $symfonyStyle)
    {
        $this->environment = $environment;
        $this->symfonyStyle = $symfonyStyle;
    }

    /**
     * Retrieves the environment associated to the current event.
     */
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * Retrieves the SymfonyStyle object previously configured in the Command class.
     */
    public function getSymfonyStyle(): SymfonyStyle
    {
        return $this->symfonyStyle;
    }
}
