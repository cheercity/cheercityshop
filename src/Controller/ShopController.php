<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{

    #[Route('/account',name: 'account') ]
    public function account(): Response
    {
        return $this->render('shop/account.html.twig',[
            'title' => 'Account',
            'subTitle' => 'Shop',
            'subTitle2' => 'Account',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/cart',name: 'cart') ]
    public function cart(): Response
    {
        return $this->render('shop/cart.html.twig',[
            'title' => 'Cart',
            'subTitle' => 'Home',
            'subTitle2' => 'Cart',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/check-out',name: 'checkOut') ]
    public function checkOut(): Response
    {
        return $this->render('shop/checkOut.html.twig',[
            'title' => 'Checkout',
            'subTitle' => 'Home',
            'subTitle2' => 'Checkout',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/full-width-shop',name: 'fullWidthShop') ]
    public function fullWidthShop(): Response
    {
        return $this->render('shop/fullWidthShop.html.twig',[
            'title' => 'Full Width Shop',
            'subTitle' => 'Home',
            'subTitle2' => 'Full Width Shop',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/> <link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script> <script src="assets/js/vendors/jquery.nstSlider.min.js"></script>',
        ]);
    }

    #[Route('/grouped-products',name: 'groupedProducts') ]
    public function groupedProducts(): Response
    {
        return $this->render('shop/groupedProducts.html.twig',[
            'title' => 'Grouped Products',
            'subTitle' => 'Home',
            'subTitle2' => 'Grouped Products',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
        ]);
    }

    #[Route('/product-details',name: 'productDetails') ]
    public function productDetails(): Response
    {
        return $this->render('shop/productDetails.html.twig',[
            'title' => 'Product',
            'subTitle' => 'Home',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>', 
        ]);
    }

    #[Route('/product-details-2',name: 'productDetails2') ]
    public function productDetails2(): Response
    {
        return $this->render('shop/productDetails2.html.twig',[
            'title' => 'Product',
            'subTitle' => 'Shop',
            'subTitle2' => 'Product',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script>',
        ]);
    }

    #[Route('/shop',name: 'shop') ]
    public function shop(): Response
    {
        return $this->render('shop/shop.html.twig',[
            'title' => 'Shop',
            'subTitle' => 'Home',
            'subTitle2' => 'Shop',
            'footer' => 'true',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/>',
            'css2' =>'<link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script> <script src="assets/js/vendors/jquery.nstSlider.min.js"></script>',
        ]);
    }

    #[Route('/sidebar-left',name: 'sidebarLeft') ]
    public function sidebarLeft(): Response
    {
        return $this->render('shop/sidebarLeft.html.twig',[
            'title' => 'Sidebar Left',
            'subTitle' => 'Shop',
            'subTitle2' => 'Sidebar Left',
            'footer' => 'flase',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/> <link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script> <script src="assets/js/vendors/jquery.nstSlider.min.js"></script>',
        ]);
    }

    #[Route('/sidebar-right',name: 'sidebarRight') ]
    public function sidebarRight(): Response
    {
        return $this->render('shop/sidebarRight.html.twig',[
            'title' => 'Sidebar Right',
            'subTitle' => 'Shop',
            'subTitle2' => 'Sidebar Right',
            'css' =>'<link rel="stylesheet" type="text/css" href="assets/css/variables/variable6.css"/> <link rel="stylesheet" type="text/css" href="assets/css/jquery.nstSlider.min.css"/>',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script> <script src="assets/js/vendors/jquery.nstSlider.min.js"></script>',
        ]);
    }

    #[Route('/variable-products',name: 'variableProducts') ]
    public function variableProducts(): Response
    {
        return $this->render('shop/variableProducts.html.twig',[
            'title' => 'Variable Products',
            'subTitle' => 'Shop',
            'subTitle2' => 'Variable Products',
            'footer' => 'true',
            'script' =>'<script src="assets/js/vendors/zoom.js"></script> <script src="assets/js/vendors/jquery.nstSlider.min.js"></script>',
        ]);
    }

}
