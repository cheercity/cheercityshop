<?php
namespace App\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class FmTestController extends AbstractController {
  #[Route('/fm/test', name: 'fm_test')]
  public function test(): JsonResponse {
    return $this->json(['ok' => true, 'route' => 'fm_test']);
  }
}
