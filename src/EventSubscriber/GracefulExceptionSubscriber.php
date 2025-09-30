<?php
namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class GracefulExceptionSubscriber implements EventSubscriberInterface
{
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(Environment $twig, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Only act on the main request. Symfony >=5.3 has isMainRequest(); older versions use Request::MAIN_REQUEST
        $isMain = true;
        if (method_exists($event, 'isMainRequest')) {
            $isMain = $event->isMainRequest();
        } else {
            // Fallback: compare the request attribute '_route_params' presence and request type
            // If the event has getRequestType use that (older Symfony), otherwise assume main.
            try {
                $const = defined('Symfony\\Component\\HttpKernel\\Kernel::MAIN_REQUEST') ? \Symfony\Component\HttpKernel\Kernel::MAIN_REQUEST : null;
                if ($const !== null && method_exists($event, 'getRequestType')) {
                    $isMain = $event->getRequestType() === $const;
                }
            } catch (\Throwable $e) {
                $isMain = true;
            }
        }
        if (!$isMain) {
            return;
        }

        $throwable = $event->getThrowable();
        $request = $event->getRequest();
        $route = $request->attributes->get('_route', '');

        // Allow toggling graceful handling via environment variable GRACEFUL_ERRORS=1
        $enabled = getenv('GRACEFUL_ERRORS') ?: ($_SERVER['GRACEFUL_ERRORS'] ?? null);
        if ($enabled === null || $enabled === '' ) {
            // Default: enabled in dev environment (so route errors during development don't take down the whole site)
            $appEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '');
            $enabled = $appEnv === 'dev';
        } else {
            $enabled = (int)$enabled > 0;
            $appEnv = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '');
        }

        if (!$enabled) {
            return;
        }

        // Skip internal or API routes: profiler, api, _wdt, debug, etc.
        $excludedPrefixes = ['_profiler', '_wdt', 'api_', 'app_debug'];
        foreach ($excludedPrefixes as $p) {
            if (str_starts_with((string)$route, $p) || stripos((string)$route, $p) !== false) {
                return;
            }
        }

        // Only gracefully handle HTML requests; APIs should receive normal JSON error codes
        $format = $request->getRequestFormat();
        if ($format && $format !== 'html') {
            return;
        }

        // Log the exception with context
        $this->logger->error(sprintf('GracefulExceptionSubscriber intercepted exception for route "%s": %s', $route, $throwable->getMessage()), [
            'exception' => $throwable,
            'route' => $route,
            'path' => $request->getPathInfo(),
        ]);

        // Render a friendly fallback page. Use 503 to indicate service issue to caches/clients.
        try {
            $templateParams = ['route' => $route];
            // In dev, expose brief error info to aid debugging in the UI (details still logged fully)
            if (($appEnv ?? '') === 'dev') {
                $templateParams['error'] = $throwable->getMessage();
                $templateParams['trace'] = $throwable->getTraceAsString();
            }
            $content = $this->twig->render('errors/partial_error.html.twig', $templateParams);
        } catch (\Throwable $e) {
            $content = '<h1>Ein Fehler ist aufgetreten</h1><p>Versuchen Sie es bitte später.</p>';
        }

        $response = new Response($content, Response::HTTP_SERVICE_UNAVAILABLE);
        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }
}
