<?php

namespace App\Shared\Controller;

use App\Shared\Entity\RsvpLog;
use App\Shared\Form\RsvpType;
use App\Shared\Repository\CmsPageRepository;
use App\Shared\Repository\RsvpLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: 'mulvaylitmas.com')]
final class LitmasController extends AbstractController
{
  public function __construct(
    private CmsPageRepository $cmsRepository
  ) {}
       
  #[Route('/', name: 'litmas_index')]
  public function index(Request $request, SessionInterface $session): Response
  {
        $naughtyOrNice = $session->get('naughty_or_nice');
        
        $textContent = $this->cmsRepository->findOneBySlug('main');
        
        return $this->render('litmas/info.html.twig', [
          'textContent'  => $textContent,
          'storyNodeKey' => 'litmas',
          'showCanvas'   => true,
          'hasSelection' => $naughtyOrNice !== null,
        ]);
    }

    #[Route('/litmas/set-choice', name: 'litmas_set_choice', methods: ['POST'])]
    public function setChoice(Request $request, SessionInterface $session): Response
    {
      $choice = $request->request->get('choice'); // 'naughty' or 'nice'
        
      if ($choice === 'naughty' || $choice === 'nice') {
        $session->set('naughty_or_nice', $choice);
        $session->set('naughty_or_nice_bool', $choice === 'naughty');
      }

      return $this->json(['success' => true]);
    }

    #[Route('/litmas/info', name: 'litmas_info')]
    public function info(Request $request): Response
    {
        $textContent = $this->cmsRepository->findOneBySlug('main');
        
        return $this->render('litmas/info.html.twig', [
          'textContent'  => $textContent,
          'storyNodeKey' => 'litmas-info',
          'showCanvas'   => false,
        ]);
    }  

    #[Route('/litmas/rsvp', name: 'litmas_rsvp')]
    public function rsvp(Request $request, SessionInterface $session,  RsvpLogRepository $rsvpRepo): Response
    {
      $rsvp   = new RsvpLog();

      $label = $session->get('naughty_or_nice');
      if ($label !== null) {
        $rsvp->setLabel($label);
      }
      else {
        $rsvp->setLabel('naughty');
      }
      
      $form   = $this->createForm(RsvpType::class, $rsvp);

      $form->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid()) {
        $rsvp->setTimestamp(new \DateTime());
        $rsvpRepo->add($rsvp, true);
        return $this->redirectToRoute('litmas_info');
      }        

      return $this->render('litmas/rsvp.html.twig', [
        'storyNodeKey' => 'litmas-rsvp',
        'showCanvas'   => false,
        'form'     => $form->createView(),
      ]);
    }

      #[Route('/litmas/location', name: 'litmas_location')]
    public function location(Request $request): Response
    {
        $textContent = $this->cmsRepository->findOneBySlug('location');
        
        return $this->render('litmas/location.html.twig', [
          'textContent' => $textContent,
          'storyNodeKey' => 'litmas-loc',
          'showCanvas'   => false,
        ]);
    }

    #[Route('/litmas/faq', name: 'litmas_faq')]
    public function faq(Request $request): Response
    {
        $textContent = $this->cmsRepository->findOneBySlug('faq');

        return $this->render('litmas/faq.html.twig', [
          'textContent'  => $textContent,
          'storyNodeKey' => 'litmas',
          'showCanvas'   => false,
        ]);
    }  
}
