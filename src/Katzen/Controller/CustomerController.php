<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\PanelView\{PanelView, PanelCard, PanelField, PanelGroup, PanelAction};
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Customer;
use App\Katzen\Form\CustomerType;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CustomerController extends AbstractController
{
  public function __construct(
    private DashboardContextService $dashboardContext,
    private CustomerRepository $customerRepo,
    private AccountingService $accountingService,
  ) {}
  
  #[Route('/customers', name: 'customer_index')]
  #[DashboardLayout('service', 'customer', 'customer-panel')]
  public function index(Request $request): Response
  {
        $activeGroup = $request->query->get('group');
        $q = $request->query->get('q');
        $customers = $this->customerRepo->findBy(['status' => 'active'], ['name' => 'ASC']);

        $cards = [];
        foreach ($customers as $c) {
            $balance = (float)$c->getAccountBalance();
            $creditLimit = (float)($c->getCreditLimit() ?? 0);
            $creditUsed = $creditLimit > 0 ? ($balance / $creditLimit) * 100 : 0;

            $statusBadge = 'success';
            if ($balance > $creditLimit * 0.9 && $creditLimit > 0) {
                $statusBadge = 'danger';
            } elseif ($balance > $creditLimit * 0.7 && $creditLimit > 0) {
                $statusBadge = 'warning';
            }

            $data = [
                'id' => (string)$c->getId(),
                'name' => $c->getName(),
                'email' => $c->getEmail(),
                'type' => $c->getType(),
                'balance' => $balance,
                'credit_limit' => $creditLimit,
                'credit_used_pct' => round($creditUsed, 1),
                'order_count' => $c->getOrders()->count(),
                'last_order' => $c->getLastOrderAt()?->format('Y-m-d') ?? 'Never',
            ];

            $card = PanelCard::create($data['id'])
                ->setTitle($data['name'])
                ->setData($data)
                ->addBadge(strtoupper($data['type']), 'info')
                ->setMeta($data['email'])
                ->addPrimaryField(PanelField::currency('balance', 'Balance'))
                ->addPrimaryField(PanelField::number('order_count', 'Orders', 0)->icon('bi-bag'))
                ->addContextField(PanelField::text('last_order', 'Last Order')->muted())
                ->addQuickAction(PanelAction::view(['name' => 'customer_show', 'params' => ['id' => $data['id']]]))
                ->addQuickAction(PanelAction::edit(['name' => 'customer_edit', 'params' => ['id' => $data['id']]]));

            if ($creditLimit > 0) {
                $card->addContextField(
                    PanelField::text('credit_used_pct', 'Credit Used')
#                        ->suffix('%')
#                        ->when($creditUsed > 90, fn($f) => $f->color('var(--color-error)'))
#                        ->when($creditUsed > 70, fn($f) => $f->color('var(--color-warning)'))
                );
            }

            if ($balance > $creditLimit && $creditLimit > 0) {
                $card->setBorderColor('var(--color-error)');
            }

            $cards[] = $card;
        }

        $groups = [
            PanelGroup::create('all', 'All Customers')->setIcon('bi-people'),
            PanelGroup::create('individual', 'Individual')->whereEquals('type', 'individual')->setIcon('bi-person'),
            PanelGroup::create('business', 'Business')->whereEquals('type', 'business')->setIcon('bi-building'),
            PanelGroup::create('wholesale', 'Wholesale')->whereEquals('type', 'wholesale')->setIcon('bi-truck'),
        ];

        $panel = PanelView::create('customers')
            ->setCards($cards)
            ->setSelectable(true)
            ->setSearchPlaceholder('Search by name, email, phone...')
            ->setEmptyState('No customers found. Add your first customer to get started!');

        foreach ($groups as $g) {
            $panel->addGroup($g);
        }

        if (in_array($activeGroup, array_map(fn($g) => $g->getKey(), $groups), true)) {
            $panel->setActiveGroup($activeGroup === 'all' ? null : $activeGroup);
        }

        $view = $panel->build();

        return $this->render('katzen/component/panel_view.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-admin.html.twig',
            'activeItem' => 'customer-panel',
            'activeMenu' => 'customer',
            'view' => $view,
            'q' => $q,
            'activeGroup' => $activeGroup ?? 'all',
            'groupSlug' => 'customer_index',
        ]));
    }

    #[Route('/customer/create', name: 'customer_create')]
    #[DashboardLayout('service', 'customer', 'customer-create')]
    public function create(Request $request): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->customerRepo->save($customer);
            $this->addFlash('success', 'Customer created successfully.');
            return $this->redirectToRoute('customer_index');
        }

        return $this->render('katzen/customer/create_customer.html.twig', $this->dashboardContext->with([
            'activeItem' => 'customer-create',
            'activeMenu' => 'customer',
            'form' => $form->createView(),
            'customer' => null,
        ]));
    }

    #[Route('/customer/{id}', name: 'customer_show')]
    #[DashboardLayout('service', 'customer', 'customer-show')]
    public function show(Request $request, Customer $customer): Response
    {
        $statement = $this->accountingService->getCustomerStatement($customer);

        return $this->render('katzen/customer/show_customer.html.twig', $this->dashboardContext->with([
            'activeItem' => 'customer-view',
            'activeMenu' => 'customer',
            'customer' => $customer,
            'statement' => $statement->getData(),
        ]));
    }

    #[Route('/customer/edit/{id}', name: 'customer_edit')]
    #[DashboardLayout('service', 'customer', 'customer-edit')]
    public function edit(Request $request, Customer $customer): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->customerRepo->save($customer);
            $this->addFlash('success', 'Customer updated successfully.');
            return $this->redirectToRoute('customer_show', ['id' => $customer->getId()]);
        }

        return $this->render('katzen/customer/create_customer.html.twig', $this->dashboardContext->with([
            'activeItem' => 'customer-edit',
            'activeMenu' => 'customer',
            'form' => $form->createView(),
            'customer' => $customer,
        ]));
    }

    #[Route('/customers/bulk', name: 'customer_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('customer_bulk', $payload['_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
        }

        $action = $payload['action'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? []);

        if (!$action || empty($ids)) {
            return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
        }

        switch ($action) {
            case 'suspend':
                foreach ($ids as $id) {
                    $customer = $this->customerRepo->find($id);
                    if ($customer) {
                        $customer->setStatus('suspended');
                    }
                }
                $this->customerRepo->flush();
                break;

            case 'activate':
                foreach ($ids as $id) {
                    $customer = $this->customerRepo->find($id);
                    if ($customer) {
                        $customer->setStatus('active');
                    }
                }
                $this->customerRepo->flush();
                break;

            case 'archive':
                foreach ($ids as $id) {
                    $customer = $this->customerRepo->find($id);
                    if ($customer) {
                        $customer->setStatus('archived');
                    }
                }
                $this->customerRepo->flush();
                break;

            default:
                return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
        }

        return $this->json(['ok' => true]);
    }
  
  #[Route('/customers/manage', name: 'customer_table')]
  #[DashboardLayout('service', 'customer', 'customer-table')]  
  public function list(Request $request): Response
  {
    $customers = $this->customerRepo->findBy(['status' => ['active','suspended']]);
    $rows = [];
    foreach ($customers as $customer) {
      $row = TableRow::create([
        'name' => $customer->getName(),
        'type' =>  $customer->getType(),        
        'numOrders' => $customer->getOrders()->count(),
        'balance' => 0.0,
        'status' =>  $customer->getStatus(),
      ]);

      $rows[] = $row;
    }
    
    $table = TableView::create('customer-table')
      ->addField(
        TableField::text('name', 'Customer Name')
          ->sortable()          
          )
      ->addField(
        TableField::text('type', 'Type')
          )
      ->addField(
        TableField::amount('numOrders', 'Orders')
          ->sortable()          
          )
      ->addField(
        TableField::currency('balance', 'Balance')
          ->sortable()          
          )
      ->addField(
        TableField::status('status', 'Status')
          )
      ->setRows($rows)
      ->setSelectable(true)
      ->setSearchPlaceholder('Type customer names, comma-separated (e.g. "Arnold, coffee, corp")')
      ->setEmptyState('No matching customers.')
      ->build();

    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'activeDash' => 'katzen/dash-admin.html.twig',
      'activeItem' => 'customer-list',
      'activeMenu' => 'customer',
      'table' => $table,
      'bulkRoute' => 'customer_bulk',
      'csrfSlug' => 'customer_bulk',
	]));                    
  } 
}
