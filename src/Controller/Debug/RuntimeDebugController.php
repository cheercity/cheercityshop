<?php

namespace App\Controller\Debug;

use App\Debug\DebugFlags;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug/runtime', name: 'debug_runtime_')]
class RuntimeDebugController extends AbstractController
{
    private array $panels = [];

    public function __construct(
        private readonly DebugFlags $flags,
        #[TaggedIterator('app.debug_panel')]
        iterable $panels,
        private readonly KernelInterface $kernel,
    ) {
        foreach ($panels as $p) {
            $this->panels[$p->getKey()] = $p;
        }
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        if (!$this->isEnvironmentAllowed()) {
            throw $this->createNotFoundException();
        }
        $this->flags->resolveForRequest($request);
        $enabledKeys = $this->flags->getEnabledPanels();
        $data = [];
        foreach ($enabledKeys as $key) {
            if (!isset($this->panels[$key])) {
                continue;
            }
            $panel = $this->panels[$key];
            if (!$panel->isAvailable()) {
                continue;
            }
            $data[$key] = [
                'label' => $panel->getLabel(),
                'result' => $panel->collect(),
            ];
        }
        if ('json' === $request->query->get('format')) {
            return new JsonResponse([
                'environment' => $this->kernel->getEnvironment(),
                'panels' => $data,
            ], 200, [], JSON_PRETTY_PRINT);
        }

        return $this->render('debug/runtime/index.html.twig', [
            'environment' => $this->kernel->getEnvironment(),
            'enabled' => $enabledKeys,
            'panels' => $data,
        ]);
    }

    #[Route('/panel/{key}', name: 'panel')]
    public function panel(string $key, Request $request): Response
    {
        if (!$this->isEnvironmentAllowed()) {
            throw $this->createNotFoundException();
        }
        $this->flags->resolveForRequest($request);
        if (!$this->flags->isEnabled($key) || !isset($this->panels[$key])) {
            throw $this->createNotFoundException('Panel not enabled');
        }
        $panel = $this->panels[$key];
        if (!$panel->isAvailable()) {
            throw $this->createNotFoundException('Panel not available');
        }
        $result = $panel->collect();
        if ('json' === $request->query->get('format')) {
            return new JsonResponse([
                'key' => $key,
                'label' => $panel->getLabel(),
                'result' => $result,
            ], 200, [], JSON_PRETTY_PRINT);
        }

        return $this->render('debug/runtime/panel.html.twig', [
            'key' => $key,
            'label' => $panel->getLabel(),
            'result' => $result,
        ]);
    }

    private function isEnvironmentAllowed(): bool
    {
        if ('dev' === $this->kernel->getEnvironment()) {
            return true;
        }

        return $this->flags->isProdAllowed();
    }
}
