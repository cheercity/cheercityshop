<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class SymOrdersDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/sym_orders-json', name: 'sym_orders_json')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): JsonResponse
    {
        try {
            $ordersLayout = $this->layouts->get('orders', 'sym_Orders');
            $result = $fm->list($ordersLayout, 200);
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
            $fmLogger->error('sym_Orders debug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $this->layouts->get('orders', 'sym_Orders')]);

            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
