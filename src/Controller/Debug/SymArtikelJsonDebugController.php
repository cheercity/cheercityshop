<?php

namespace App\Controller\Debug;

use App\Service\FileMakerClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug', name: 'debug_')]
final class SymArtikelJsonDebugController extends AbstractController
{
    public function __construct(private string $artikelLayout = 'sym_Artikel')
    {
    }

    #[Route('/sym_artikel-json', name: 'debug_sym_artikel_json', methods: ['GET'])]
    public function listJson(FileMakerClient $fm, Request $request, LoggerInterface $logger): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 200);

        try {
            $resp = $fm->list($this->artikelLayout, $limit);
            $rows = $resp['response']['data'] ?? [];

            $out = [];
            foreach ($rows as $r) {
                $fd = $r['fieldData'] ?? [];
                $published = trim((string) ($fd['Published'] ?? $fd['published'] ?? '0'));
                if ('1' !== $published) {
                    continue;
                }

                $out[] = [
                    'recordId' => $r['recordId'] ?? null,
                    'fieldData' => $fd,
                ];
            }

            return new JsonResponse(['count' => count($out), 'rows' => $out, 'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM)]);
        } catch (\Throwable $e) {
            $logger->error('sym_Artikel JSON debug error: '.$e->getMessage(), ['exception' => $e]);

            return new JsonResponse(['error' => 'Failed to fetch layout', 'layout' => $this->artikelLayout, 'message' => $e->getMessage()], 500);
        }
    }
}
