<?php

namespace App\Profile\Controller;

use App\Shared\Waitlist\WaitlistSubmissionHandler; # writes to katzen_waitlist; TODO: replace after UnifiedWaitlist project
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
  public function waitlistSubmit(Request $request, WaitlistSubmissionHandler $handler): JsonResponse
  {
    $outcome = $handler->handle($request, WaitlistSubmissionHandler::SOURCE_GREGISHERE);

    if (!$outcome->looksSuccessful()) {
      return new JsonResponse(['success' => false, 'message' => $outcome->error], $outcome->httpStatus);
    }

    return new JsonResponse([
      'success' => true,
      'message' => 'Message received!',
      # 'questionnaire_url' => $this->generateUrl('seer_questionnaire'),
      # 'questionnaire_mode' => 'overlay' // 'redirect'
    ]);
  }
}
