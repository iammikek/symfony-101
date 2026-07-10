<?php

namespace App\Controller\Shop;

use App\Form\ItemFilterFormType;
use App\Form\ItemFormType;
use App\Service\ItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shop/items')]
class ShopItemController extends AbstractController
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly ItemService $itemService)
    {
    }

    #[Route('', name: 'shop_item_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $filterForm = $this->createForm(ItemFilterFormType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $data = $filterForm->getData();
            if ($data['name_contains']) {
                $filters['name_contains'] = $data['name_contains'];
            }
            if ($data['category']) {
                $filters['category_id'] = $data['category']->getId();
            }
            if ($data['min_price'] !== null) {
                $filters['min_price'] = $data['min_price'];
            }
            if ($data['max_price'] !== null) {
                $filters['max_price'] = $data['max_price'];
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $skip = ($page - 1) * self::PAGE_SIZE;

        [$items, $total] = $this->itemService->listItems($skip, self::PAGE_SIZE, $filters);
        $totalPages = max(1, (int) ceil($total / self::PAGE_SIZE));

        return $this->render('shop/item_list.html.twig', [
            'items' => $items,
            'total_count' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'filter_form' => $filterForm,
            'query_params' => $request->query->all(),
        ]);
    }

    #[Route('/new', name: 'shop_item_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): Response
    {
        $form = $this->createForm(ItemFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $category = $data['category'];

            $item = $this->itemService->create(
                $data['name'],
                $data['description'] ?? null,
                number_format((float) $data['price'], 2, '.', ''),
                $category?->getId(),
            );

            $this->addFlash('success', sprintf('Created "%s".', $item->getName()));

            return $this->redirectToRoute('shop_item_detail', ['id' => $item->getId()]);
        }

        return $this->render('shop/item_form.html.twig', [
            'form' => $form,
            'page_title' => 'Add item',
        ]);
    }

    #[Route('/{id}', name: 'shop_item_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): Response
    {
        $item = $this->itemService->getById($id);

        return $this->render('shop/item_detail.html.twig', [
            'item' => $item,
        ]);
    }
}
