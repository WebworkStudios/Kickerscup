<?php


declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Container\Contracts\ContainerInterface;
use App\Infrastructure\Container\Exceptions\ContainerException;
use App\Infrastructure\Container\Exceptions\NotFoundException;
use App\Infrastructure\Session\Contracts\FlashMessageInterface;

#[Injectable]
#[Singleton]
class FlashMessageProvider
{
    private ?FlashMessageInterface $flashInstance = null;

    public function __construct(
        private readonly ContainerInterface $container
    )
    {
    }

    /**
     * Liefert die FlashMessage-Instanz, erstellt sie bei Bedarf
     */
    public function getFlashMessage(): FlashMessageInterface
    {
        if ($this->flashInstance === null) {
            try {
                $this->flashInstance = $this->container->get(FlashMessageInterface::class);
            } catch (NotFoundException|ContainerException) {
            }
        }

        return $this->flashInstance;
    }
}