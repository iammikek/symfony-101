<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class ShopFlashSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/shop')) {
            return;
        }

        $this->requestStack->getSession()->getFlashBag()->add('success', 'You are logged in.');
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/shop')) {
            return;
        }

        $this->requestStack->getSession()->getFlashBag()->add('info', 'You are logged out.');
    }
}
