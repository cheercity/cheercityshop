<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/' )]
    #[Route('/index',name: 'index') ]
    public function index(): Response
    {
        return $this->render('home/index.html.twig',[
            'header' => 'flase',
            'footer' => 'true',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable1.css'>",
            'script' => "<link rel='stylesheet' href='assets/js/vendors/zoom.js'>",
        ]);
    }

    #[Route('/all-category',name: 'allCategory') ]
    public function allCategory(): Response
    {
        return $this->render('home/allCategory.html.twig',[
            'title' => 'All Catagory',
            'subTitle' => 'Home',
            'subTitle2' => 'All Catagory',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable6.css'>",
        ]);
    }

    #[Route('/category',name: 'category') ]
    public function category(): Response
    {
        return $this->render('home/category.html.twig',[
            'title' => 'Catagory',
            'subTitle' => 'Home',
            'subTitle2' => 'Catagory',
            'footer' => 'true',
            'css' => '<link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/> <link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'script' => '<script src="assets/js/vendors/jquery.nstSlider.min.js"></script> <script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/external-products',name: 'externalProducts') ]
    public function externalProducts(): Response
    {
        return $this->render('home/externalProducts.html.twig',[
            'title' => 'External Product',
            'subTitle' => 'Home',
            'subTitle2' => 'External Product',
            'css' => '<link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/> <link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
        ]);
    }

    #[Route('/index-eight',name: 'indexEight') ]
    public function indexEight(): Response
    {
        return $this->render('home/indexEight.html.twig',[
            'header' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable4.css"/>',
        ]);
    }

    #[Route('/index-five',name: 'indexFive') ]
    public function indexFive(): Response
    {
        return $this->render('home/indexFive.html.twig',[
            'header' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable4.css"/>',
        ]);
    }

    #[Route('/index-four',name: 'indexFour') ]
    public function indexFour(): Response
    {
        return $this->render('home/indexFour.html.twig',[
            'header' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable3.css"/>',
        ]);
    }

    #[Route('/index-nine',name: 'indexNine') ]
    public function indexNine(): Response
    {
        return $this->render('home/indexNine.html.twig',[
            'header' => 'flase',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable1.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/index-seven',name: 'indexSeven') ]
    public function indexSeven(): Response
    {
        return $this->render('home/indexSeven.html.twig',[
            'header' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable7.css"/>',
        ]);
    }

    #[Route('/index-six',name: 'indexSix') ]
    public function indexSix(): Response
    {
        return $this->render('home/indexSix.html.twig',[
            'header' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable5.css"/>',
        ]);
    }

    #[Route('/index-ten',name: 'indexTen') ]
    public function indexTen(): Response
    {
        return  $this->render('home/indexTen.html.twig',[
            'header' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable10.css"/>',
        ]);
    }

    #[Route('/index-three',name: 'indexThree') ]
    public function indexThree(): Response
    {
        return $this->render('home/indexThree.html.twig',[
            'header' => 'flase',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable2.css'>",
        ]);
    }

    #[Route('/index-two',name: 'indexTwo') ]
    public function indexTwo(): Response
    {
        return $this->render('home/indexTwo.html.twig',[
            'header' => 'flase',
            'footer' => 'true',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable1.css'>",
        ]);
    }

    #[Route('/login',name: 'login') ]
    public function login(): Response
    {
        return  $this->render('home/login.html.twig',[
            'title' => 'Log In',
            'subTitle' => 'home',
            'subTitle2' => 'Log In',
            'footer' => 'true',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable6.css'>",
        ]);
    }

    #[Route('/out-of-stock-products',name: 'outOfStockProducts') ]
    public function outOfStockProducts(): Response
    {
        return $this->render('home/outOfStockProducts.html.twig',[
            'title' => 'Out Of Stock',
            'subTitle' => 'home',
            'subTitle2' => 'Out Of Stock',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable6.css'>",
        ]);
    }

    #[Route('/shop-five-column',name: 'shopFiveColumn') ]
    public function shopFiveColumn(): Response
    {
        return $this->render('home/shopFiveColumn.html.twig',[
            'header' => 'true',
            'title' => 'Shop Five Column',
            'subTitle' => 'home',
            'subTitle2' => 'Shop Five Column',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable1.css'> <link rel='stylesheet' href='assets/css/jquery.nstSlider.min.css'>",
        ]);
    }

    #[Route('/simple-products',name: 'simpleProducts') ]
    public function simpleProducts(): Response
    {
        return $this->render('home/simpleProducts.html.twig',[
            'title' => 'Simple Products',
            'subTitle' => 'home',
            'subTitle2' => 'Simple Products',
        ]);
    }

    #[Route('/thank-you',name: 'thankYou') ]
    public function thankYou(): Response
    {
        return $this->render('home/thankYou.html.twig',[
            'title' => 'Thank You',
            'subTitle' => 'home',
            'subTitle2' => 'Thank You',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable1.css'>",
        ]);
    }

    #[Route('/wishlist',name: 'wishlist') ]
    public function wishlist(): Response
    {
        return $this->render('home/wishlist.html.twig',[
            'title' => 'wishlist',
            'subTitle' => 'home',
            'subTitle2' => 'wishlist',
            'css' => "<link rel='stylesheet' href='assets/css/variables/variable1.css'>",
        ]);
    }
}
