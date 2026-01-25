<?php

namespace App\Shared\Controller;

use App\Katzen\Entity\KatzenWaitlist;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class KatzenDemoController extends AbstractController
{
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
    
    $entityManager->persist($waitlist);
    $entityManager->flush();

    $session = $request->getSession();
    $session->set('waitlist_email', $email);
    $session->set('waitlist_timestamp', time());
    
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

    if (!$email) {
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

    $session = $request->getSession();
    $email = $session->get('waitlist_email', $email);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      if ($request->isXmlHttpRequest()) {
        return new JsonResponse([
          'success' => false,
          'message' => 'Please enter a valid email address.'
        ], 400);
      }
      $this->addFlash('error', 'Session expired. Please join the waitlist again.');
      return $this->redirectToRoute('katzen_demo');
    }

    $waitlistRepo = $entityManager->getRepository(KatzenWaitlist::class);
    $waitlist = $waitlistRepo->findOneBy(['email' => $email]);
    
    if ($waitlist) {
      $waitlist->setBusinessName($request->request->get('business_name'));
      $waitlist->setBusinessType($request->request->get('business_type'));
      $waitlist->setBiggestChallenge($request->request->get('biggest_challenge'));
      $waitlist->setQuestionnaireCompletedAt(new \DateTimeImmutable());
      
      $entityManager->flush();
    }

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
}
