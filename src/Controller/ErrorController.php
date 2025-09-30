<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Routing\Annotation\Route;

class ErrorController extends AbstractController
{
    public function show(FlattenException $exception, ?DebugLoggerInterface $logger = null, ?LoggerInterface $appLogger = null): Response
    {
        // Forward to the public error route so the URL /error is used to display errors.
        // We attach the status code and text as request attributes for the error route.
        $statusCode = $exception->getStatusCode();
        $statusText = $exception->getStatusText();

        $request = Request::create('/error');
        $request->attributes->set('status_code', $statusCode);
        $request->attributes->set('status_text', $statusText);
        // also expose the FlattenException for richer debug templates
        $request->attributes->set('exception', $exception);

        try {
            return $this->forward(self::class.'::errorPage', ['request' => $request]);
        } catch (\Throwable $e) {
            // Log the exception details to the application logger for debugging
            if ($appLogger) {
                $appLogger->error('ErrorController forward failed: ' . $e->getMessage(), ['exception' => $e]);
            }
            // If forwarding fails, fallback to a simple response
            return new Response("<html><body><h1>Error {$statusCode}</h1><p>{$statusText}</p></body></html>", $statusCode);
        }
    }

    #[Route('/error', name: 'app_error')]
    public function errorPage(Request $request): Response
    {
        $statusCode = $request->attributes->get('status_code', 500);
        $statusText = $request->attributes->get('status_text', 'Internal Server Error');
        $exception = $request->attributes->get('exception');

        $template = match ((int) $statusCode) {
            404 => 'error/404.html.twig',
            403 => 'error/403.html.twig',
            500 => 'error/500.html.twig',
            default => 'error/error.html.twig',
        };

        try {
            // If an exception is available, log its message and trace for debugging
            if ($exception instanceof FlattenException) {
                /** @var \Throwable|null $previous */
                $previous = $exception->getPrevious();
                // Use the container logger if available
                try {
                    $logger = $this->container->has('logger') ? $this->container->get('logger') : null;
                    if ($logger && $previous) {
                        $logger->error('Rendering error page for exception: ' . $previous->getMessage(), ['exception' => $previous]);
                    } elseif ($logger) {
                        $logger->error('Rendering error page: ' . $exception->getMessage());
                    }
                } catch (\Throwable) {
                    // ignore logging errors here
                }

            }
            return $this->render($template, [
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'exception' => $exception,
            ], new Response('', $statusCode));
        } catch (\Throwable $e) {
            // log and fallback
            try {
                $logger = $this->container->has('logger') ? $this->container->get('logger') : null;
                if ($logger) {
                    $logger->critical('Error rendering error template: ' . $e->getMessage(), ['exception' => $e]);
                }
            } catch (\Throwable) {
                // ignore
            }
            return new Response("<html><body><h1>Error {$statusCode}</h1><p>{$statusText}</p></body></html>", $statusCode);
        }
    }
}
