<?php

namespace App\Profile\Controller;

use App\Katzen\Entity\KatzenWaitlist; # TODO: replace after UnifiedWaitlist project
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%gregishere_match%'")]
final class SeerController extends AbstractController
{

  public function __construct(
  ) {}

  #[Route('/', name: 'seer_home')]
  public function index(): Response { return $this->render('seer/index.html.twig', []);}

  
  #[Route('/about', name: 'seer_about')]
  public function about(): Response { return $this->render('seer/about.html.twig', []);}

  #[Route('/contact', name: 'seer_contact')]
  public function contact(): Response  { return $this->render('seer/contact.html.twig', []);}
  
  #[Route('/custom', name: 'seer_custom')]
  public function custom(): Response { return $this->render('seer/custom.html.twig', []);}

  #[Route('/work', name: 'seer_work')]
  public function work(): Response { return $this->render('seer/work.html.twig', []);}
  
  #[Route('/faq', name: 'seer_faq')]
  public function faq(): Response { return $this->render('seer/faq.html.twig', []);}


  #[Route('/contact/submit', name: 'seer_waitlist_submit', methods: ['POST'])]
  public function waitlistSubmit(Request $request, EntityManagerInterface $entityManager): JsonResponse
  {
    $submittedToken = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('waitlist', $submittedToken)) {
      return new JsonResponse([
        'success' => false,
        'message' => 'Invalid security token. Please refresh and try again.'
      ], 400);
    }
    $email = $request->request->get('email');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse([
	'success' => false,
        'message' => 'Please enter a valid email address.'
      ], 400);
    }

    $waitlist = new KatzenWaitlist();
    $waitlist->setEmail($email);
    $waitlist->setCreatedAt(new \DateTimeImmutable());
    $waitlist->setStatus('pending');
    $waitlist->setSessionId($request->getSession()->getId());

    $session = $request->getSession();
    $session->set('waitlist_email', $email);
    $session->set('waitlist_timestamp', time());
    
    $entityManager->persist($waitlist);
    $entityManager->flush();

    return new JsonResponse([
      'success' => true,
      'message' => 'Message received!',
      # 'questionnaire_url' => $this->generateUrl('seer_questionnaire'),
      # 'questionnaire_mode' => 'overlay' // 'redirect'
    ]);
  }
}
