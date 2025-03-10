<?php

namespace App\Controller;

use App\Entity\Item;
use App\Form\ItemType;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        return $this->render('item/index.html.twig', [
          'controller_name' => 'ItemController',
          'items'           => $itemRepository->findAll(),
          'item_form'       => $form
        ]);
    }

  #[Route('/item/{id}', name: 'item')]
  public function show(Request $request, Item $item, ItemRepository $itemRepository): Response
  {
        return $this->render('item/show.html.twig', [
          'item' => $item,
        ]);
  }
}
