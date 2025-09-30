<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class NavigationDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/navigation', name: 'navigation')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): Response
    {
        // Navigation aus sym_Navigation laden
        // Filter: Status = 1 (oder ähnlich)
        $navigation = [];
        $error = null;

        try {
            $layout = $this->layouts->get('navigation', 'sym_Navigation');
            $result = $fm->list($layout, 1000);
            foreach ($result['response']['data'] ?? [] as $r) {
                $navigation[] = $r['fieldData'] ?? [];
            }

            // TODO: Navigation-spezifische Logik hier implementieren
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('NavigationDebug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout ?? 'sym_Navigation']);
            $error = $e->getMessage();
        }

        return $this->render('debug/navigation/index.html.twig', [
            'navigation' => $navigation,
            'error' => $error,
        ]);
    }
}
