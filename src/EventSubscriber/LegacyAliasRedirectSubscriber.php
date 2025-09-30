<?php

namespace App\EventSubscriber;

use App\Service\NavService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LegacyAliasRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(private NavService $nav, private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // run on exceptions so normal routing is unaffected
            ExceptionEvent::class => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $ex = $event->getThrowable();
        if (!$ex instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $path = ltrim((string) $request->getPathInfo(), '/');
        if ('' === $path) {
            return;
        }

        // Only handle simple single-segment paths (no slashes) to avoid interfering with other routes
        if (false !== strpos($path, '/')) {
            return;
        }

        $aliasMap = $this->nav->getAliasToCatSort();
        $key = strtolower($path);
        if (!isset($aliasMap[$key])) {
            return;
        }

        $target = '/kategorie/'.$key;
        $this->logger->info('Redirecting legacy alias path to canonical category route', ['from' => $path, 'to' => $target]);

        $response = new RedirectResponse($target, 301);
        $event->setResponse($response);
    }
}
