<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SimpleBannerDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/banner-simple', name: 'banner_simple')]
    public function simple(): Response
    {
        return $this->render('debug/banner/simple.html.twig', [
            'message' => 'Einfacher Banner Test - Template funktioniert!',
        ]);
    }

    #[Route('/banner-json', name: 'banner_json')]
    public function bannerJson(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            $layout = $this->layouts->get('banner', 'sym_Banner');
            $result = $fm->list($layout, 10);

            return new JsonResponse([
                'status' => 'success',
                'fm_response' => $result,
                'record_count' => count($result['response']['data'] ?? []),
            ], 200, []);
        } catch (\Throwable $e) {
            // Log using Symfony logger. Keep original exception details in the log but return a safe JSON payload.
            $logger->error('FileMaker bannerJson error: '.$e->getMessage(), [
                'exception' => $e,
                'layout' => $this->layouts->get('banner', 'sym_Banner'),
            ]);

            return new JsonResponse([
                'status' => 'error',
                'error' => 'Internal FileMaker error (logged)',
            ], 200, []);
        }
    }
}
