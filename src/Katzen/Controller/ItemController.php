<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\Item;
use App\Katzen\Form\ItemType;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Service\Delete\DeleteMode;
use App\Katzen\Service\Delete\ItemDeletionPolicy;
use App\Katzen\Service\Utility\DashboardContextService;
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
    private DashboardContextService $dashboardContext,
  ) {
  }
  
  #[Route('/item', name: 'item_index')]
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

        return $this->render('katzen/item/index.html.twig', $this->dashboardContext->with([
          'activeItem'      => 'items',
          'activeMenu'      => 'stock',
          'items'           => $items,
          'categories'      => $categories,
          'selectedCategory'=> $selected,
          'item_form'       => $form
        ]));
    }

  #[Route('/item/{id}', name: 'item_show')]
  public function show(Request $request, Item $item, ItemRepository $itemRepository): Response
  {
        return $this->render('katzen/item/show.html.twig', $this->dashboardContext->with([
          'activeItem' => 'stock', # TODO: 'item',
          'activeMenu' => 'stock',          
          'item' => $item,
        ]));
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

  #[Route('/item/delete/{id}', name: 'item_delete', methods: ['POST'])]
  public function delete(Request $request, Item $item, ItemDeletionPolicy $policy): Response
  {
    $this->denyAccessUnlessGranted('ROLE_USER');

    $token = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('delete_item_'.$item->getId(), $token)) {
      throw $this->createAccessDeniedException('Invalid CSRF token.');
    }

    $mode = DeleteMode::from($request->request->get('mode', DeleteMode::BLOCK_IF_REFERENCED->value));
    
    $report = $policy->preflight($item, $mode);

    if (!$report->ok) {
      $this->addFlash('danger', sprintf(
        "Can't delete: %s (Used by %d recipe%s)",
        implode(' ', $report->reasons),
        $report->facts['recipe_ref_count'],
        $report->facts['recipe_ref_count'] === 1 ? '' : 's'
      ));

      return $this->redirectToRoute('item_show', ['id' => $item->getId()]);
    }

    $policy->execute($item, $mode);
    
    $this->addFlash('success', 'Item deleted.');
    return $this->redirectToRoute('item_index');
  }
}
