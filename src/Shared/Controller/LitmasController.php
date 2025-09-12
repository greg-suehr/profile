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

final class LitmasController extends AbstractController
{
    public function __construct(
      private CmsPageRepository $cmsRepository
    ) {}
       
    #[Route('/litmas', name: 'litmas_index')]
    public function index(Request $request): Response
    {
        $textContent = $this->cmsRepository->findBySlug('main');
        
        return $this->render('litmas/info.html.twig', [
          'textContent'  => $textContent,
          'storyNodeKey' => 'litmas',
          'showCanvas'   => true,
        ]);
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
    public function rsvp(Request $request, RsvpLogRepository $rsvpRepo): Response
    {
      $rsvp   = new RsvpLog();
      $form   = $this->createForm(RsvpType::class, $rsvp);

      $form->handleRequest($request);

      if ($form->isSubmitted() && $form->isValid()) {
        $rsvp->setTimestamp(new \DateTime());
        $rsvpRepo->add($rsvp, true);
        return $this->render('litmas/info.html.twig', [
          'storyNodeKey' => 'litmas-confirm',
          'showCanvas'   => false,
        ]);
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
        $textContent = $this->cmsRepository->findBySlug('location');
        
        return $this->render('litmas/location.html.twig', [
          'textContent' => $textContent,
          'storyNodeKey' => 'litmas-loc',
          'showCanvas'   => false,
        ]);
    }

    #[Route('/litmas/faq', name: 'litmas_faq')]
    public function faq(Request $request): Response
    {
        $textContent = $this->cmsRepository->findBySlug('faq');

        return $this->render('litmas/faq.html.twig', [
          'textContent'  => $textContent,
          'storyNodeKey' => 'litmas',
          'showCanvas'   => false,
        ]);
    }  
}
