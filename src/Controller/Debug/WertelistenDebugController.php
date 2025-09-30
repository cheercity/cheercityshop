<?php

namespace App\Controller\Debug;

use App\Service\WertelistenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class WertelistenDebugController extends AbstractController
{
    #[Route('/_debug/wertelisten/farbcode', name: 'debug_wertelisten_farbcode', methods: ['GET'])]
    public function index(WertelistenService $wertelisten): JsonResponse
    {
        $map = $wertelisten->getMap('Farbcode');
        return $this->json($map);
    }
}
