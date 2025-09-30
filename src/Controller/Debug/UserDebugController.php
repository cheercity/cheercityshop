<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class UserDebugController extends AbstractController
{
    #[Route('/users/test', name: 'users_test')]
    public function test(FileMakerClient $fm, \Symfony\Component\HttpFoundation\Request $request, LoggerInterface $logger): JsonResponse
    {
        $attempts = [];
        // 1) allow explicit layout via query param for quick tests
        $explicit = $request->query->get('layout');
        if ($explicit) {
            $layouts = [$explicit];
        } else {
            // 2) allow configuration via env var FM_USER_LAYOUTS (comma-separated)
            $env = $_ENV['FM_USER_LAYOUTS'] ?? $_SERVER['FM_USER_LAYOUTS'] ?? null;
            if ($env) {
                $layouts = array_map('trim', explode(',', $env));
            } else {
                // 3) default fallbacks
                $layouts = ['sym_Users', 'Users', 'Kunden', 'Customer'];
            }
        }

        foreach ($layouts as $layout) {
            try {
                $result = $fm->list($layout, 1);
                $count = count($result['response']['data'] ?? []);

                return new JsonResponse([
                    'status' => 'success',
                    'layout' => $layout,
                    'record_count' => $count,
                    'fm_response' => $result,
                ], 200, []);
            } catch (\Throwable $e) {
                $logger->warning('User layout test failed for layout '.$layout.': '.$e->getMessage(), ['exception' => $e]);
                $attempts[] = ['layout' => $layout, 'error' => $e->getMessage()];
                // try next layout
            }
        }

        return new JsonResponse([
            'status' => 'error',
            'message' => 'No usable layouts found for users',
            'attempts' => $attempts,
        ], 200, []);
    }

    #[Route('/users', name: 'users')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): Response
    {
        $users = [];
        $error = null;

        // User/Kunden aus verschiedenen Layouts versuchen
        $possibleLayouts = ['sym_Users', 'Users', 'Kunden', 'Customer'];

        foreach ($possibleLayouts as $layout) {
            try {
                // Erst ohne Sort-Parameter versuchen
                $result = $fm->list($layout, 10);

                if (isset($result['response']['data']) && !empty($result['response']['data'])) {
                    $users = array_map(fn ($r) => [
                        'layout' => $layout,
                        'recordId' => $r['recordId'] ?? 'N/A',
                        'data' => $r['fieldData'] ?? [],
                    ], $result['response']['data']);

                    // Layout gefunden und Daten erhalten - weitere Daten mit Sort laden
                    try {
                        // FileMakerClient::list() doesn't support server-side sort for GET — fallback to find()
                        $sortedResult = $fm->find($layout, [], [
                            'limit' => 50,
                            'sort' => [
                                ['fieldName' => 'Created', 'sortOrder' => 'descend'],
                            ],
                        ]);

                        if (isset($sortedResult['response']['data'])) {
                            $users = array_map(fn ($r) => [
                                'layout' => $layout,
                                'recordId' => $r['recordId'] ?? 'N/A',
                                'data' => $r['fieldData'] ?? [],
                            ], $sortedResult['response']['data']);
                        }
                    } catch (\Throwable $sortError) {
                        // Sort funktioniert nicht, aber wir haben bereits Basis-Daten
                        $error = 'Sort failed: '.$sortError->getMessage().' (using unsorted data)';
                    }

                    break; // Layout erfolgreich gefunden
                }
            } catch (\Throwable $e) {
                $fmLogger = $logger;
                if ($this->container->has('monolog.logger.filemaker')) {
                    $fmLogger = $this->container->get('monolog.logger.filemaker');
                }
                $fmLogger->error('Error while loading users layout '.$layout.': '.$e->getMessage(), ['exception' => $e]);
                $error = "Layout '$layout': ".$e->getMessage();
                continue; // Nächstes Layout versuchen
            }
        }

        return $this->render('debug/users/index.html.twig', [
            'users' => $users ?? [],
            'error' => $error ?? null,
        ]);
    }
}
