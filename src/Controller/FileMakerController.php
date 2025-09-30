<?php

// src/Controller/FileMakerController.php

namespace App\Controller;

use App\Service\FileMakerClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/fm')]
final class FileMakerController extends AbstractController
{
    public function __construct(private FileMakerClient $fm)
    {
    }

    #[Route('/auth', name: 'fm_auth', methods: ['POST'])]
    public function auth(): JsonResponse
    {
        try {
            $this->fm->ensureToken();
        } catch (\Throwable $e) {
            // Auth failed
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 401);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/{layout}/find', name: 'fm_find', methods: ['POST'])]
    public function find(string $layout, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $query = $payload['query'] ?? [];
        $opts = array_diff_key($payload, ['query' => true]);

        try {
            $res = $this->fm->find($layout, $query, $opts);

            return $this->json($res, 200);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{layout}', name: 'fm_create', methods: ['POST'])]
    public function create(string $layout, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $fieldData = $payload['fieldData'] ?? [];
        $portalData = $payload['portalData'] ?? [];
        if (!$fieldData) {
            return $this->json(['error' => 'fieldData required'], 422);
        }
        try {
            $res = $this->fm->create($layout, $fieldData, $portalData);

            return $this->json($res, 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{layout}/{recordId}', name: 'fm_edit', methods: ['PATCH'])]
    public function edit(string $layout, int $recordId, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $fieldData = $payload['fieldData'] ?? [];
        $portalData = $payload['portalData'] ?? [];
        $modId = $payload['modId'] ?? null;
        if (!$fieldData && !$portalData) {
            return $this->json(['error' => 'fieldData or portalData required'], 422);
        }
        try {
            $res = $this->fm->edit($layout, $recordId, $fieldData, $modId, $portalData);

            return $this->json($res, 200);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{layout}/{recordId}', name: 'fm_delete', methods: ['DELETE'])]
    public function delete(string $layout, int $recordId): JsonResponse
    {
        try {
            $res = $this->fm->delete($layout, $recordId);

            return $this->json($res, 200);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
