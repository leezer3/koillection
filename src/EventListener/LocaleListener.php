<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Enum\LocaleEnum;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[AsEventListener(event: 'kernel.request', priority: 15)]
#[AsEventListener(event: 'security.interactive_login')]
#[AsEntityListener(event: Events::postUpdate, entity: User::class, lazy: true)]
final class LocaleListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $defaultLocale
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->query->get('_locale');

        if ($locale && \in_array($locale, LocaleEnum::LOCALES)) {
            $request->getSession()->set('_locale', $locale);
            $request->setLocale($request->getSession()->get('_locale', $locale));
        } else {
            $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
        }
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (null !== $user->getLocale()) {
            $this->requestStack->getSession()->set('_locale', $user->getLocale());
        }
    }

    public function postUpdate(User $user): void
    {
        if ($this->requestStack->getMainRequest()) {
            $this->requestStack->getSession()->set('_locale', $user->getLocale());
        }
    }
}
