<?php

namespace App\Controller;

use App\Entity\InviteLog;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HyperLinkController extends AbstractController
{
  private EntityManagerInterface $em;

  public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

  private function eventForNode(string $node): ?string
  {
        return [
          'birthday'     => 'Viewed invite',
          'birthdayYes'  => 'Said yes',
          'birthdayNo'   => 'Said no',
        ][$node] ?? null;
    }

  private function logEvent(SessionInterface $session, string $node): void
  {
        if (!$event = $this->eventForNode($node)) {
          return;
        }
        
        $log = (new InviteLog())
            ->setCode($session->get('inviteCode', '??????'))
            ->setEvent($event)
            ->setTimestamp(new DateTime());
        
        $this->em->persist($log);
        $this->em->flush();
    }
  
  
  #[Route('/story', name: 'hyperlink_index')]
  public function index(SessionInterface $session): Response
  {
        $storyNodeKey = $session->get('story.currentNode', 'birthday');
        
        $noCanvasMap = array(
          'prologue1' => 1,
          'prologue2' => 1,
        );

        $unimplementedMap = array(
          'hollow1' => 1,
          'pantheon1' => 1,
        );

        if ( array_key_exists($storyNodeKey, $unimplementedMap) ) {
          $storyNodeKey = 'birthday';
        }

        if ($storyNodeKey === 'birthday') {
          $this->logEvent($session, $storyNodeKey);
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

  #[Route('/rsvp/{code?}', name: 'hyperlink_rsvp')]
  public function rsvp(SessionInterface $session, ?string $code = null): Response
  {
    if ( $code && strlen($code) > 6 ) {
      $code = substr($code,0,6);
    }
    
    $session->set('inviteCode', $code ?: '??????');

    $this->logEvent($session, 'birthday');

    return $this->render('hyper_link/story.html.twig', [
      'storyNodeKey' => 'birthday',
      'showCanvas'   => true,
    ]);
    
    }
  
    #[Route('/hyperlink/jump/{nextNodeKey}', name: 'hyperlink_jump')]
    public function jump(string $nextNodeKey, SessionInterface $session): Response
    {
        $session->set('story.currentNode', $nextNodeKey);

        $this->logEvent($session, $nextNodeKey);

        return $this->redirectToRoute('hyperlink_index');
    }

    #[Route('/story/clear', name: 'hyperlink_clear')]
    public function clear(SessionInterface $session): Response
    {
	$session->set('story.currentNode', 'prologue1');

	return $this->redirectToRoute('hyperlink_index');
    }
}
