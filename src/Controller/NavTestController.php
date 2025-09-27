<?php
namespace App\Controller;

use App\Service\NavService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NavTestController extends AbstractController
{
    #[Route('/navtest', name: 'navtest')]
    public function index(NavService $nav): Response
    {
        $items = $nav->getMenu('sym_Navigation', ['Status' => '1'], 300);
        return $this->render('navtest.html.twig', [
            'items' => $items,
        ]);
    }
}
