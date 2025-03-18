<?php

namespace App\Controller;

use App\Repository\RecipientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final class NotifyListController extends AbstractController
{
  #[Route('/notify', name: 'app_notify_list')]
  public function index(Environment $twig, RecipientRepository $recipientRepository): Response
  {
    return new Response($twig->render('notify_list/index.html.twig', [
      'recipients' => $recipientRepository->findAll(),
    ]));
  }
}
