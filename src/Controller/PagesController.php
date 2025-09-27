<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route('/about',name: 'about') ]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig',[
            'title' => 'About',
            'subTitle' => 'Pages',
            'subTitle2' => 'About',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'footer' => 'true',
        ]);
    }

    #[Route('/error-page',name: 'errorPage') ]
    public function errorPage(): Response
    {
        return $this->render('pages/errorPage.html.twig',[
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
        ]);
    }

    #[Route('/faq',name: 'faq') ]
    public function faq(): Response
    {
        return $this->render('pages/faq.html.twig',[
            'title' => 'Faq',
            'subTitle' => 'Pages',
            'subTitle2' => 'Faq',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable1.css"/>',
            'footer' => 'true',
        ]);
    }
}
