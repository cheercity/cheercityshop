<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SymBannerJsonController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/sym_banner-json', name: 'sym_banner_json')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            $layout = $this->layouts->get('banner', 'sym_Banner');
            $result = $fm->list($layout, 200);
            $rows = [];
            foreach ($result['response']['data'] ?? [] as $r) {
                $rows[] = $r['fieldData'] ?? [];
            }

            return new JsonResponse(['status' => 'success', 'data' => $rows, 'record_count' => count($rows), 'fm_response' => $result['response'] ?? null]);
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('sym_Banner debug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout]);

            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
