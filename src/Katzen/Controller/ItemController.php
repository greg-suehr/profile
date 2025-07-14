<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\Item;
use App\Katzen\Form\ItemType;
use App\Katzen\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse; 
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ItemController extends AbstractController
{
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  
  #[Route('/item', name: 'app_item')]
  public function index(Request $request, ItemRepository $itemRepository): Response
  {
        $proto_item = new Item();
        $form = $this->createForm(ItemType::class, $proto_item);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
          $this->entityManager->persist($proto_item);
          $this->entityManager->flush();

          return $this->redirectToRoute('app_item');
        }

        $categories = $itemRepository->createQueryBuilder('i')
            ->select('DISTINCT i.category')
            ->orderBy('i.category', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $categories = array_map(fn($c) => $c['category'], $categories);
        $selected = $request->query->get('category');

        if ($selected) {
            $items = $itemRepository->findBy(['category' => $selected], ['name' => 'ASC']);
        } else {
            $items = $itemRepository->findBy([], ['category' => 'ASC', 'name' => 'ASC']);
        }

        return $this->render('katzen/item/index.html.twig', [
          'items'           => $items,
          'categories'      => $categories,
          'selectedCategory'=> $selected,
          'item_form'       => $form
        ]);
    }

  #[Route('/item/{id}', name: 'item')]
  public function show(Request $request, Item $item, ItemRepository $itemRepository): Response
  {
        return $this->render('katzen/item/show.html.twig', [
          'item' => $item,
        ]);
  }

  #[Route('/items/search', name: 'item_search')]
  public function search(Request $request, ItemRepository $itemRepo): JsonResponse
  {
    $query = $request->query->get('q', '');
    $items = $itemRepo->searchByName($query);
    
    return $this->json(array_map(fn($item) => [
      'id' => $item->getId(),
      'name' => $item->getName(),
    ], $items));
}
}
