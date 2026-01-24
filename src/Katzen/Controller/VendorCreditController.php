<?php

namespace App\Katzen\Controller;

use App\Katzen\Entity\VendorCredit;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/credits/vendor', name: 'vendor_credit_', host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
final class VendorCreditController extends AbstractController
{
  #[Route('/', name: 'index')]
  #[DashboardLayout('finance', 'vendor-credit', 'credit-table')]
  public function index(): Response
  {
    return $this->redirectToRoute('costing_dashboard');
  }
  
  #[Route('/create', name: 'create')]
  public function create(Request $request): Response
  {
    return $this->redirectToRoute('costing_dashboard');
  }    
    
  #[Route('/{id}/apply', name: 'apply', methods: ['POST'])]
  public function apply(VendorCredit $credit, Request $request): Response
  {
    return $this->redirectToRoute('costing_dashboard');
  }    
}
