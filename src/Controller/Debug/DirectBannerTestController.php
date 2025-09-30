<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class DirectBannerTestController extends AbstractController
{
    #[Route('/banner-direct', name: 'banner_direct')]
    public function direct(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            // Erst mal nur die FileMaker-Verbindung testen
            $reflection = new \ReflectionClass($fm);
            $tokenProperty = $reflection->getProperty('token');
            $tokenProperty->setAccessible(true);

            $hostProperty = $reflection->getProperty('host');
            $hostProperty->setAccessible(true);

            $dbProperty = $reflection->getProperty('db');
            $dbProperty->setAccessible(true);

            return new JsonResponse([
                'status' => 'connection_test',
                'host' => $hostProperty->getValue($fm),
                'database' => $dbProperty->getValue($fm),
                'token' => $tokenProperty->getValue($fm) ? 'SET' : 'NULL',
                'class' => get_class($fm),
            ], 200, []);
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('DirectBannerTestController direct error: '.$e->getMessage(), ['exception' => $e]);

            return new JsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 3),
            ], 500, []);
        }
    }

    #[Route('/banner-auth', name: 'banner_auth')]
    public function auth(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            // Token erzwingen
            $reflection = new \ReflectionClass($fm);
            $ensureTokenMethod = $reflection->getMethod('ensureToken');
            $ensureTokenMethod->setAccessible(true);

            $ensureTokenMethod->invoke($fm);

            $tokenProperty = $reflection->getProperty('token');
            $tokenProperty->setAccessible(true);

            return new JsonResponse([
                'status' => 'auth_success',
                'token_length' => strlen($tokenProperty->getValue($fm) ?? ''),
                'timestamp' => date('Y-m-d H:i:s'),
            ], 200, []);
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('DirectBannerTestController auth error: '.$e->getMessage(), ['exception' => $e]);

            return new JsonResponse([
                'status' => 'auth_error',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500, []);
        }
    }
}
