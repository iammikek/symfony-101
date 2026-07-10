<?php

namespace App\EventSubscriber;

use App\Exception\CategoryInUseException;
use App\Exception\CategoryNameExistsException;
use App\Exception\CategoryNotFoundException;
use App\Exception\ItemNotFoundException;
use App\Exception\UserEmailExistsException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ItemNotFoundException) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'Item not found', 'code' => 'ITEM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND,
            ));

            return;
        }

        if ($exception instanceof CategoryNotFoundException) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'Category not found', 'code' => 'CATEGORY_NOT_FOUND'],
                Response::HTTP_NOT_FOUND,
            ));

            return;
        }

        if ($exception instanceof CategoryInUseException) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'Category has items and cannot be deleted', 'code' => 'CATEGORY_IN_USE'],
                Response::HTTP_CONFLICT,
            ));

            return;
        }

        if ($exception instanceof CategoryNameExistsException) {
            $event->setResponse(new JsonResponse(
                [
                    'detail' => sprintf("Category name '%s' already exists", $exception->name),
                    'code' => 'CATEGORY_NAME_EXISTS',
                ],
                Response::HTTP_CONFLICT,
            ));

            return;
        }

        if ($exception instanceof UserEmailExistsException) {
            $event->setResponse(new JsonResponse(
                [
                    'detail' => sprintf("User email '%s' already exists", $exception->email),
                    'code' => 'USER_EMAIL_EXISTS',
                ],
                Response::HTTP_CONFLICT,
            ));

            return;
        }

        if ($exception instanceof AuthenticationException || $exception instanceof UnauthorizedHttpException) {
            $event->setResponse(new JsonResponse(
                ['detail' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED,
            ));

            return;
        }

        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            $message = $exception->getMessage();
            if ($message === 'Rate limit exceeded') {
                $event->setResponse(new JsonResponse(
                    ['detail' => 'Rate limit exceeded', 'code' => 'RATE_LIMIT_EXCEEDED'],
                    Response::HTTP_TOO_MANY_REQUESTS,
                ));

                return;
            }

            $event->setResponse(new JsonResponse(
                ['detail' => 'Forbidden'],
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
