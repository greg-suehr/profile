<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotifyListController extends AbstractController
{
    #[Route('/', name: 'app_notify_list')]
    public function index(): Response
    {
#        return $this->render('notify_list/index.html.twig', [
#            'controller_name' => 'NotifyListController',
#        ]);
      return new Response(<<<EOF
                          <html>
                            <body>
                              <h1>Welcome!</h1>
                              <p>This page is still under construction.</p>
                              <div><img src="/images/construction.webp"/>
                              <p>We appreciate your patience!</p>
                            </body>
                          </html>
                          EOF
      );
    }
}
