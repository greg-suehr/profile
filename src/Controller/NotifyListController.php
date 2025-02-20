<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NotifyListController extends AbstractController
{
    #[Route('/', name: 'app_notify_list')]
    public function index(Request $request): Response
    {
      $greet = '<h1>Welcome!</h1>';
      if ($name = $request->query->get('hello')) {
        $greet = sprintf('<h1>Hey %s!</h1>', htmlspecialchars($name));
      }
      return new Response(<<<EOF
                          <html>
                            <body>
                              $greet
                              <p>This page is still under construction.</p>
                              <div><img src="/images/construction.webp"/>
                              <p>We appreciate your patience!</p>
                            </body>
                          </html>
                          EOF
      );
    }
}
