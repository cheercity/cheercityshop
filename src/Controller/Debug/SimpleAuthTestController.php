<?php

namespace App\Controller\Debug;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SimpleAuthTestController extends AbstractController
{
    #[Route('/simple-auth-test', name: 'simple_auth_test')]
    public function simpleAuthTest(LoggerInterface $logger): JsonResponse
    {
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment_check' => [],
            'status' => 'testing',
        ];

        try {
            // Environment-Variablen prüfen
            $envVars = ['FM_HOST', 'FM_DB', 'FM_USER', 'FM_PASS'];
            foreach ($envVars as $var) {
                $value = $_ENV[$var] ?? null;
                $result['environment_check'][$var] = [
                    'exists' => !empty($value),
                    'value_preview' => $value ? (strlen($value) > 20 ? substr($value, 0, 20).'...' : $value) : 'NOT SET',
                ];
            }

            // Prüfe, ob alle Environment-Variablen gesetzt sind
            $allSet = true;
            foreach ($envVars as $var) {
                if (empty($_ENV[$var] ?? null)) {
                    $allSet = false;
                    break;
                }
            }

            if (!$allSet) {
                $result['status'] = 'error';
                $result['message'] = 'Nicht alle Environment-Variablen sind gesetzt';

                return new JsonResponse($result, 200, []);
            }

            // Einfacher cURL-Test für Authentifizierung
            $host = $_ENV['FM_HOST'];
            $db = $_ENV['FM_DB'];
            $user = $_ENV['FM_USER'];
            $pass = $_ENV['FM_PASS'];

            $auth_url = $host.'/fmi/data/vLatest/databases/'.$db.'/sessions';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $auth_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode((object) []),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic '.base64_encode($user.':'.$pass),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $result['curl_test'] = [
                'url' => $auth_url,
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'response_preview' => $response ? substr($response, 0, 200).'...' : 'No response',
            ];

            if ($curlError) {
                $result['status'] = 'error';
                $result['message'] = 'cURL Error: '.$curlError;
            } elseif ($httpCode >= 400) {
                $result['status'] = 'error';
                $result['message'] = 'HTTP Error: '.$httpCode;

                // Versuche JSON zu parsen für FileMaker-Fehler
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['messages'])) {
                    $result['filemaker_error'] = $responseData['messages'];
                }
            } else {
                $responseData = json_decode($response, true);
                if ($responseData && isset($responseData['response']['token'])) {
                    $result['status'] = 'success';
                    $result['message'] = 'Authentication successful';
                    $result['token_preview'] = substr($responseData['response']['token'], 0, 20).'...';
                } else {
                    $result['status'] = 'error';
                    $result['message'] = 'No token in response';
                    $result['full_response'] = $responseData;
                }
            }
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('SimpleAuthTestController exception: '.$e->getMessage(), ['exception' => $e]);
            $result['status'] = 'error';
            $result['message'] = 'Exception: '.$e->getMessage();
            $result['exception_details'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        return new JsonResponse($result, 200, []);
    }

    #[Route('/simple-connection-test', name: 'simple_connection_test')]
    public function simpleConnectionTest(LoggerInterface $logger): JsonResponse
    {
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => [],
        ];

        try {
            // Environment-Variablen prüfen
            $envVars = ['FM_HOST', 'FM_DB', 'FM_USER', 'FM_PASS'];
            foreach ($envVars as $var) {
                $value = $_ENV[$var] ?? null;
                $result['tests'][$var] = [
                    'exists' => !empty($value),
                    'value' => $value ? (strlen($value) > 20 ? substr($value, 0, 20).'...' : $value) : 'NOT SET',
                ];
            }

            $result['status'] = 'success';
            $result['message'] = 'Environment check completed';
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('SimpleAuthTestController simpleConnectionTest exception: '.$e->getMessage(), ['exception' => $e]);
            $result['status'] = 'error';
            $result['message'] = 'Exception: '.$e->getMessage();
        }

        return new JsonResponse($result, 200, []);
    }
}
