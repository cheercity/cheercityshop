<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use App\Service\FileMakerLayoutRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
class ContentDebugController extends AbstractController
{
    public function __construct(private FileMakerLayoutRegistry $layouts)
    {
    }

    #[Route('/content', name: 'content')]
    public function index(FileMakerClient $fm, LoggerInterface $logger): Response
    {
        $content = [];
        $error = null;

        try {
            $layout = $this->layouts->get('content', 'sym_Content');
            $result = $fm->list($layout, 1000);
            foreach ($result['response']['data'] ?? [] as $r) {
                $content[] = $r['fieldData'] ?? [];
            }
        } catch (\Throwable $e) {
            $fmLogger = $logger;
            if ($this->container->has('monolog.logger.filemaker')) {
                $fmLogger = $this->container->get('monolog.logger.filemaker');
            }
            $fmLogger->error('ContentDebug error: '.$e->getMessage(), ['exception' => $e, 'layout' => $layout ?? 'sym_Content']);
            $error = $e->getMessage();
        }

        return $this->render('debug/content/index.html.twig', [
            'content' => $content,
            'error' => $error,
        ]);
    }
}
