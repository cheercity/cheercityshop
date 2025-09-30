<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class AuthTestController extends AbstractController
{
    #[Route('/auth-debug', name: 'auth_debug')]
    public function authDebug(): Response
    {
        return $this->render('debug/auth/index.html.twig');
    }

    #[Route('/auth-test', name: 'auth_test')]
    public function authTest(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'test_steps' => [],
        ];

        try {
            // Step 1: Service-Instanziierung testen
            $result['test_steps'][] = [
                'step' => 1,
                'name' => 'Service instantiation',
                'status' => 'success',
                'message' => 'FileMakerClient service injected successfully',
            ];

            // Step 2: Token-Status vor Authentifizierung prüfen
            $reflector = new \ReflectionClass($fm);
            $tokenProperty = $reflector->getProperty('token');
            $tokenProperty->setAccessible(true);
            $currentToken = $tokenProperty->getValue($fm);

            $result['test_steps'][] = [
                'step' => 2,
                'name' => 'Initial token state',
                'status' => 'info',
                'message' => $currentToken ? 'Token already exists: '.substr($currentToken, 0, 20).'...' : 'No token yet (expected)',
            ];

            // Step 3: Direkter Auth-Test über private ensureToken Methode
            $ensureTokenMethod = $reflector->getMethod('ensureToken');
            $ensureTokenMethod->setAccessible(true);

            // Token zurücksetzen für sauberen Test
            $tokenProperty->setValue($fm, null);

            $ensureTokenMethod->invoke($fm);

            // Step 4: Token nach Authentifizierung prüfen
            $newToken = $tokenProperty->getValue($fm);

            if ($newToken) {
                $result['test_steps'][] = [
                    'step' => 3,
                    'name' => 'Authentication',
                    'status' => 'success',
                    'message' => 'Token obtained successfully',
                    'token_preview' => substr($newToken, 0, 20).'...',
                ];
            } else {
                $result['test_steps'][] = [
                    'step' => 3,
                    'name' => 'Authentication',
                    'status' => 'error',
                    'message' => 'No token received after authentication',
                ];
            }

            // Step 5: Token-Validität testen (zweiter ensureToken-Aufruf sollte nichts tun)
            $ensureTokenMethod->invoke($fm);
            $tokenAfterSecondCall = $tokenProperty->getValue($fm);

            $result['test_steps'][] = [
                'step' => 4,
                'name' => 'Token persistence',
                'status' => $newToken === $tokenAfterSecondCall ? 'success' : 'warning',
                'message' => $newToken === $tokenAfterSecondCall ? 'Token persists correctly' : 'Token changed on second call',
            ];

            $result['overall_status'] = 'success';
            $result['message'] = 'Authentication test completed successfully';
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $result['test_steps'][] = [
                'step' => 'error',
                'name' => 'Exception occurred',
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            $result['overall_status'] = 'error';
            $result['message'] = 'Authentication test failed: '.$e->getMessage();
            $fmLogger->error('AuthTestController authTest exception: '.$e->getMessage(), ['exception' => $e]);
        }

        return new JsonResponse($result, 200, []);
    }

    #[Route('/connection-test', name: 'connection_test')]
    public function connectionTest(): JsonResponse
    {
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment_check' => [],
        ];

        // Environment-Variablen prüfen
        $envVars = ['FM_HOST', 'FM_DB', 'FM_USER', 'FM_PASS'];
        foreach ($envVars as $var) {
            $value = $_ENV[$var] ?? null;
            $result['environment_check'][$var] = [
                'exists' => !empty($value),
                'value_preview' => $value ? (strlen($value) > 20 ? substr($value, 0, 20).'...' : $value) : null,
            ];
        }

        // Basis-Konnektivitätstest
        $host = $_ENV['FM_HOST'] ?? '';
        if ($host) {
            $result['connectivity_test'] = $this->testConnectivity($host);
        }

        return new JsonResponse($result, 200, []);
    }

    private function testConnectivity(string $host): array
    {
        $result = [
            'host' => $host,
            'tests' => [],
        ];

        // Parse URL
        $parsed = parse_url($host);
        $hostname = $parsed['host'] ?? $host;
        $port = $parsed['port'] ?? 443;

        // DNS-Test
        $ip = gethostbyname($hostname);
        $result['tests']['dns'] = [
            'status' => $ip !== $hostname ? 'success' : 'error',
            'message' => $ip !== $hostname ? "Resolved to: $ip" : 'Could not resolve hostname',
        ];

        // Port-Test (einfach)
        if ($ip !== $hostname) {
            $connection = @fsockopen($ip, $port, $errno, $errstr, 5);
            $result['tests']['port'] = [
                'status' => $connection ? 'success' : 'error',
                'message' => $connection ? "Port $port is reachable" : "Port $port not reachable: $errstr",
            ];
            if ($connection) {
                fclose($connection);
            }
        }

        return $result;
    }
}
