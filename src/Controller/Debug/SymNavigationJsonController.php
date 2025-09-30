<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SymNavigationJsonController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/sym_navigation-json', name: 'sym_navigation_json')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            $layout = $this->layouts->get('navigation', 'sym_Navigation');
            $result = $fm->list($layout, 500);
            $rows = [];
            foreach ($result['response']['data'] ?? [] as $r) {
                $rows[] = $r['fieldData'] ?? [];
            }

            return new JsonResponse(['status' => 'success', 'data' => $rows]);
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('sym_Navigation debug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout]);

            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
