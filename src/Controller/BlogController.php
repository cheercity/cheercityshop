<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    #[Route('/contact', name: 'contact') ]
    public function contact(): Response
    {
        $av = $this->getAssetVersion();

        return $this->render('blog/contact.html.twig', [
            'title' => 'Contact',
            'subTitle' => 'Blog',
            'subTitle2' => 'Contact',
            'css' => ['assets/css/variables/variable6.css'],
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

    #[Route('/news', name: 'news') ]
    public function news(): Response
    {
        return $this->render('blog/news.html.twig', [
            'title' => 'News',
            'subTitle' => 'Blog',
            'subTitle2' => 'News',
            'footer' => 'true',
        ]);
    }

    #[Route('/news-details', name: 'newsDetails') ]
    public function newsDetails(): Response
    {
        return $this->render('blog/newsDetails.html.twig', [
            'title' => 'News Details',
            'subTitle' => 'Blog',
            'subTitle2' => 'News Details',
            'footer' => 'true',
        ]);
    }

    #[Route('/news-grid', name: 'newsGrid') ]
    public function newsGrid(): Response
    {
        return $this->render('blog/newsGrid.html.twig', [
            'title' => 'News Grid',
            'subTitle' => 'Blog',
            'subTitle2' => 'News Grid',
            'footer' => 'true',
        ]);
    }
}
