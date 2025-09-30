<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SymArtikelItemsDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/sym_artikel_items-json', name: 'sym_artikel_items_json')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            $layout = $this->layouts->get('artikel_items', 'sym_Artikel_items');
            $result = $fm->list($layout, 200);
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
            $fmLogger->error('sym_Artikel_items debug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout]);

            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
