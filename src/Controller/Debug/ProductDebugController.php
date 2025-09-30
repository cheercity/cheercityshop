<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class ProductDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/products', name: 'products')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): Response
    {
        $products = [];
        $error = null;

        try {
            $layout = $this->layouts->get('product', 'sym_Product');
            $result = $fm->list($layout, 100);
            foreach ($result['response']['data'] ?? [] as $r) {
                $products[] = $r['fieldData'] ?? [];
            }
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('ProductDebug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout ?? 'sym_Product']);
            $error = $e->getMessage();
        }

        return $this->render('debug/products/index.html.twig', [
            'products' => $products,
            'error' => $error,
        ]);
    }
}
