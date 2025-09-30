<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/about', name: 'about') ]
    public function about(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('pages/about.html.twig', [
            'title' => 'About',
            'subTitle' => 'Pages',
            'subTitle2' => 'About',
            'css' => ['assets/css/variables/variable6.css'],
            'footer' => 'true',
        ]);
    }

    #[Route('/error-page', name: 'errorPage') ]
    public function errorPage(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('pages/errorPage.html.twig', [
            'footer' => 'true',
            'css' => ['assets/css/variables/variable6.css'],
        ]);
    }

    #[Route('/faq', name: 'faq') ]
    public function faq(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('pages/faq.html.twig', [
            'title' => 'Faq',
            'subTitle' => 'Pages',
            'subTitle2' => 'Faq',
            'css' => ['assets/css/variables/variable1.css'],
            'footer' => 'true',
        ]);
    }

    private function getAssetVersion(): string
    {
        try {
            return (string) $this->getParameter('asset_version');
        } catch (\Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException $e) {
            return (string) time();
        }
    }
}
