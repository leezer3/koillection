<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Twig\Environment;
use Twig\Extension\CoreExtension;

#[AsEventListener(event: 'kernel.request')]
final class TimezoneListener
{
    public function __construct(
        private readonly Security $security,
        private readonly Environment $twig
    ) {
    }

    public function onKernelRequest(): void
    {
        if ($this->security->getUser() instanceof User) {
            $this->twig->getExtension(CoreExtension::class)->setTimezone($this->security->getUser()->getTimezone());
        }
    }
}
