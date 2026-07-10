<?php

namespace App\Controller\Shop;

use App\Service\ItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shop')]
class ShopHomeController extends AbstractController
{
    public function __construct(private readonly ItemService $itemService)
    {
    }

    #[Route('', name: 'shop_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('shop/home.html.twig', [
            'stats' => $this->itemService->getStats(),
        ]);
    }
}
