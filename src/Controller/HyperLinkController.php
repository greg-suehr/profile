<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HyperLinkController extends AbstractController
{
    #[Route('/story', name: 'hyperlink_index')]
    public function index(SessionInterface $session): Response
    {
        $storyNodeKey = $session->get('story.currentNode', 'prologue1');

        $noCanvasMap = array(
          'prologue1' => 1,
          'prologue2' => 1,
        );

        $unimplementedMap = array(
          'hollow1' => 1,
          'pantheon1' => 1,
        );

        if ( array_key_exists($storyNodeKey, $unimplementedMap) ) {
          $storyNodeKey = 'prologue1';
        }
        
        return $this->render('hyper_link/story.html.twig', [        
          'storyNodeKey' => $storyNodeKey,
          'showCanvas'     => !array_key_exists($storyNodeKey, $noCanvasMap),
        ]);
    }
  
    #[Route('/alt/{storyNodeKey}', name: 'hyperlink_alt')]
    public function alt(string $storyNodeKey, SessionInterface $session): Response
    {
        return $this->render('hyper_link/story.html.twig', [        
          'storyNodeKey' => $storyNodeKey,
          'showCanvas'   => true,
        ]);
    }

  
    #[Route('/hyperlink/jump/{nextNodeKey}', name: 'hyperlink_jump')]
    public function jump(string $nextNodeKey, SessionInterface $session): Response
    {
        $session->set('story.currentNode', $nextNodeKey);

        return $this->redirectToRoute('hyperlink_index');
    }

    #[Route('/story/clear', name: 'hyperlink_clear')]
    public function clear(SessionInterface $session): Response
    {
	$session->set('story.currentNode', 'prologue1');

	return $this->redirectToRoute('hyperlink_index');
    }
}
