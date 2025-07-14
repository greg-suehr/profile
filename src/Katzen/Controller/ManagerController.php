<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\Items;
use App\Katzen\Entity\Recipe;
use App\Katzen\Repository\ItemRepository;
use App\Katzen\Repository\RecipeRepository;
use App\Katzen\Service\RecipeImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Seld\JsonLint\JsonParser;

final class ManagerController extends AbstractController
{
  #[Route('/dashboard', name: 'dashboard_home')]
  public function index(): Response
  {
        return $this->render('katzen/manager/dashboard.html.twig', [
          'active'     => 'home',
          'activeItem' => 'home',                    
          'activeMenu' => 'home',
          'user' => array('firstName' => 'Greg', 'lastName' => 'Suehr', 'role' => 'Captain',
                          'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg'
          ),
          'encouragement' => array(
            'header_message' => 'Ready to crush it?',
          ),
          'alerts' => array(
            'alert_text' => 'You have 2 orders waiting for approval and 3 items low in stock.',
          ),
          'notifications' => [0, 1, 2],
          'widgets' => [array('title' => 'KPI', 'value' => '100%')],
        ]);
    }

  #[Route('/menus', name: 'menu_index')]
  public function menus(Request $request): Response
  {
       return $this->render('katzen/manager/dashboard.html.twig', [
         'active' => 'coming_soon',
         'description' => 'Menu creation coming soon!',
         'user' => array('firstName' => 'Greg' ),         
       ]);

    }

  #[Route('/stock', name: 'stock_index')]
  public function stockt(Request $request, ItemRepository $itemRepo): Response
  {        
        return $this->render('katzen/recipe/list.html.twig', [
          'recipes'         => $itemRepo->findAll(),
        ]);
    }

  #[Route('/schedules', name: 'schedule_index')]
  public function schedules(Request $request): Response
    {
        return $this->render('katzen/manager/dashboard.html.twig', [
          'active' => 'coming_soon',
          'description' => 'Scheduling coming soon!'
        ]);
    }

  #[Route('/notifications', name: 'notifications')]
  public function notifications(Request $request): Response
  {
        return $this->render('katzen/manager/dashboard.html.twig', [
          'active' => 'coming_soon',
          'description' => 'Notifications coming soon!'
        ]);
    }
}
