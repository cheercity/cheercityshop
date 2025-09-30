<?php

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class RebuildProductController extends AbstractController
{
    #[Route('/rebuild/products', name: 'rebuild_products')]
    public function index(): Response
    {
        $file = __DIR__ . '/../../data/products.json';
        if (!file_exists($file)) {
            return new Response('products.json missing', 500);
        }

        $data = json_decode(file_get_contents($file), true);

        return $this->render('rebuild/products.html.twig', [
            'products' => $data,
        ]);
    }

    #[Route('/rebuild/product/{slug}', name: 'rebuild_product')]
    public function show(string $slug): Response
    {
        $file = __DIR__ . '/../../data/products.json';
        $data = json_decode(file_get_contents($file), true);
        $found = null;
        foreach ($data as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $found = $p;
                break;
            }
        }

        if (null === $found) {
            throw $this->createNotFoundException('Product not found');
        }

        return $this->render('rebuild/product.html.twig', [
            'product' => $found,
        ]);
    }
}
