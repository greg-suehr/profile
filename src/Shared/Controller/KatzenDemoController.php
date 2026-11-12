<?php

namespace App\Shared\Controller;

use App\Katzen\Entity\KatzenWaitlist;
use App\Shared\Waitlist\WaitlistSubmissionHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: 'katzen_', condition: "request.getHost() matches '%getkatzen_marketing_match%'")]
final class KatzenDemoController extends AbstractController
{
  private const SESSION_ENTRY_ID = 'waitlist_entry_id';

  #[Route('/__debug/host', name: 'debug_host')]
  public function debugHost(Request $request): Response
  {
    return new Response($request->getHost());
  }

  #[Route('/', name: 'demo')]
  #[Route('/demo', name: 'demo2')]
  public function landing(Request $request): Response
  {
    return $this->render('katzen_demo/landing.html.twig');
  }
  
  #[Route('/waitlist/submit', name: 'waitlist_submit', methods: ['POST'])]
  public function waitlistSubmit(Request $request, WaitlistSubmissionHandler $handler): JsonResponse
  {
    $outcome = $handler->handle($request, WaitlistSubmissionHandler::SOURCE_GETKATZEN);

    if (!$outcome->looksSuccessful()) {
      return new JsonResponse(['success' => false, 'message' => $outcome->error], $outcome->httpStatus);
    }

    if ($outcome->entry) {
      $session = $request->getSession();
      $session->set(self::SESSION_ENTRY_ID, $outcome->entry->getId());
      $session->set('waitlist_email', $outcome->entry->getEmail());
    }

    // TODO: Send confirmation email
    // $this->sendWaitlistConfirmation($email);

    return new JsonResponse([
      'success' => true,
      'message' => 'Successfully added to waitlist!',
      'questionnaire_url' => $this->generateUrl('katzen_questionnaire'),
      'questionnaire_mode' => 'overlay' // 'redirect'
    ]);
  }

  #[Route('/waitlist/questionnaire', name: 'questionnaire')]
  public function questionnaire(Request $request): Response
  {
    $session = $request->getSession();
    $email = $session->get('waitlist_email');

    if (!$email || !$session->has(self::SESSION_ENTRY_ID)) {
      $this->addFlash('warning', 'Please join our waitlist first.');
      return $this->redirectToRoute('katzen_demo');
    }
    
    return $this->render('katzen_demo/questionnaire.html.twig', [
      'email' => $email
    ]);
  }

  private function sendWaitlistConfirmation(string $email): void
  {
    $email = (new Email())
         ->from('hello@getkatzen.com')
         ->to($email)
         ->subject('Welcome to the Katzen Waitlist')
         ->html($this->renderView('emails/waitlist_confirmation.html.twig'));
    $this->mailer->send($email);
  }

  #[Route('/waitlist/questionnaire/submit', name: 'questionnaire_submit', methods: ['POST'])]
  public function questionnaireSubmit(Request $request, EntityManagerInterface $entityManager): JsonResponse|Response
  {
    $submittedToken = $request->request->get('_token');
    if (!$this->isCsrfTokenValid('questionnaire', $submittedToken)) {
      if ($request->isXmlHttpRequest()) {
        return new JsonResponse([
          'success' => false,
          'message' => 'Invalid security token. Please refresh and try again.'
        ], 400);
      }
      throw $this->createAccessDeniedException('Invalid CSRF token');
    }

    $entryId = $request->getSession()->get(self::SESSION_ENTRY_ID);
    $waitlist = \is_int($entryId) ? $entityManager->find(KatzenWaitlist::class, $entryId) : null;

    if (!$waitlist) {
      if ($request->isXmlHttpRequest()) {
        return new JsonResponse([
          'success' => false,
          'message' => 'Session expired. Please join the waitlist again.'
        ], 400);
      }
      $this->addFlash('error', 'Session expired. Please join the waitlist again.');
      return $this->redirectToRoute('katzen_demo');
    }

    $input = $request->request->all();
    $waitlist->setBusinessName(self::text($input, 'business_name', 255));
    $waitlist->setBusinessType(self::text($input, 'business_type', 255));
    $waitlist->setBiggestChallenge(self::text($input, 'biggest_challenge', 5000));
    $waitlist->setQuestionnaireCompletedAt(new \DateTimeImmutable());

    $entityManager->flush();

    $message = 'Got it! Our team will review the information you provided and reach out within 3-5 business days.';

    if ($request->isXmlHttpRequest()) {
      return new JsonResponse([
        'success' => true,
        'message' => $message,
      ]);
    }
    
    $this->addFlash('success', $message);
    return $this->redirectToRoute('katzen_demo');
  }

  private static function text(array $input, string $key, int $max): ?string
  {
    $value = $input[$key] ?? null;
    if (!\is_string($value) || '' === trim($value)) {
      return null;
    }

    return mb_substr(trim($value), 0, $max);
  }
}
