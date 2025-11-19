<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\PanelView\{PanelView, PanelCard, PanelField, PanelGroup, PanelAction};
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Component\ShowPage\{ShowPage, ShowPageHeader, ShowPageFooter, PageSection, PageAction};
use App\Katzen\Entity\Customer;
use App\Katzen\Form\CustomerType;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Audit\AuditService;
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
    private AuditService $audit,
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
                ->addQuickAction(PanelAction::view('customer_show'))
                ->addQuickAction(PanelAction::edit('customer_edit'))
              ;

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
            'form' => $form->createView(),
            'customer' => null,
        ]));
    }

    #[Route('/customer/{id}', name: 'customer_show')]
    #[DashboardLayout('service', 'customer', 'customer-show')]
    public function show(Request $request, Customer $customer): Response
    {
        $statementResult = $this->accountingService->getCustomerStatement($customer);
        $statement = $statementResult->getData();
        
        $ordersTable = $this->buildOrdersTable($customer);
        $invoicesTable = $this->buildInvoicesTable($customer);
        $activityTable = $this->buildActivityTable($customer);

        $page = ShowPage::create('customer-show')
          ->setHeader(
            ShowPageHeader::create()
                    ->setTitle($customer->getName())
                    ->setSubtitle('Customer ID: ' . $customer->getId())
                    ->setStatusBadge($customer->isActive() ? 'Active' : 'Inactive',
                                     $customer->isActive() ? 'success' : 'secondary')
                    ->addTab('info', 'Overview', 'bi-person')
                    ->addTab('transactions', 'Transactions', 'bi-receipt')
                    ->addTab('activity', 'Activity Log', 'bi-clock-history')
                    ->addQuickAction(
                      PageAction::edit('customer_edit')
                        ->setRoute('customer_edit', ['id' => $customer->getId() ])
                    )
              )
          ->addSection(
            PageSection::createInfoBox('Contact Details')
              ->setTab('info')
              ->setWidth(6)
              ->setColumns(2)
              ->addItem('Name', $customer->getName())
              ->addItem('Email', $customer->getEmail()) # TODO: 'link', 'mailto:' . $customer->getEmail()
              ->addItem('Phone', $customer->getPhone())
              ->addItem('Account #', $customer->getId())
              )
          ->addSection(
            PageSection::createInfoBox('Address')
              ->setTab('info')
              ->setWidth(4)
              ->setColumns(1)
              ->addItem('Shipping Address', $customer->getShippingAddress())
              ->addItem('Billing Address', $customer->getBillingAddress())
              )
          ->addSection(
            PageSection::createInfoBox('Financial Details')
              ->setTab('info')
              ->setWidth(6)
              ->setColumns(2)
              ->addItem('Account Balance', $customer->getAccountBalance())
              ->addItem('Invoiced Balance', $customer->getArBalance())
              ->addItem('Credit Limit', $customer->getCreditLimit())
              ->addItem('Credit Status', $customer->getStatus() == 'suspended' ? 'Over Limit' : 'OK') # TODO: not this
              ->addItem('Payment Terms', $customer->getPaymentTerms())
              )
          ->addSection(
            PageSection::createInfoBox('Customer Segment')
              ->setTab('info')
              ->setWidth(4)
              ->setColumns(1)
              ->addItem('Customer Type', $customer->getType())
              ->addItem('Notes', $customer->getNotes())
              )
          ->addSection(
            PageSection::createTable('Recent Orders')
              ->setTab('transactions')
              ->setTableView($ordersTable)
              )
          ->addSection(
            PageSection::createTable('Recent Invoices')
              ->setTab('transactions')
              ->setTableView($invoicesTable)
              )
          ->addSection(
            PageSection::createTable('Recent Activity')
              ->setTab('activity')
              ->setTableView($activityTable)
              )
          ->setFooter(
            ShowPageFooter::create()
              ->addSummary('Total Invoiced (period)',
                           '$' . number_format($statement['summary']['total_invoiced'], 2))
              ->addSummary('Total Paid (period)',
                           '$' . number_format($statement['summary']['total_invoiced'], 2))
              ->addSummary('Outstanding Balance', 
                           '$' . number_format($customer->getAccountBalance(), 2),
                           'text-danger')
            )
            ->build();

        return $this->render('katzen/component/show_page.html.twig', $this->dashboardContext->with([
            'customer' => $customer,
            'page' => $page,
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
        'balance' => $customer->getAccountBalance(),
        'status' =>  $customer->getStatus(),
      ])
      ->setId($customer->getId());

      $rows[] = $row;
    }
    
    $table = TableView::create('customer-table')
      ->addField(
        TableField::link('name', 'Customer Name', 'customer_show')
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
      ->addQuickAction(TableAction::edit('customer_edit'))
      ->setSearchPlaceholder('Type customer names, comma-separated (e.g. "Arnold, coffee, corp")')
      ->setEmptyState('No matching customers.')
      ->build();

    return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
      'table' => $table,
      'bulkRoute' => 'customer_bulk',
      'csrfSlug' => 'customer_bulk',
	]));                    
  }

  private function buildOrdersTable(Customer $customer): array
  {        
    $rows = [];
        
    foreach ($customer->getOrders() as $o) {
      $row = TableRow::create([
        'order_number' => $o->getId(),
        'order_date' => $o->getCreatedAt(),
        'order_total' => $o->getTotalAmount(),
      ])
      ->setId($o->getId());
        
      $rows[] = $row;
    }
          
        
    return TableView::create('customer-orders')
          ->addField(
            TableField::link('order_number', 'Order #', 'order_show')
              )
          ->addField(
            TableField::date('order_date', 'Date')
              )
          ->addField(
            TableField::amount('order_total', 'Total')
              )
          ->setRows($rows)
          ->setShowToolbar(false)
          ->build();
    }

  private function buildInvoicesTable(Customer $customer): array
  {        
    $rows = [];
        
    foreach ($customer->getInvoices() as $o) {
      $row = TableRow::create([
        'invoice_number' => $o->getId(),
        'invoice_date' => $o->getCreatedAt(),
        'invoice_total' => $o->getTotalAmount(),
      ])
      ->setId($o->getId());
        
      $rows[] = $row;
    }
          
        
    return TableView::create('customer-invoices')
          ->addField(
            TableField::link('invoice_number', 'Invoice #', 'invoice_show')
              )
          ->addField(
            TableField::date('invoice_date', 'Date')
              )
          ->addField(
            TableField::amount('invoice_total', 'Total')
              )
          ->setRows($rows) 
          ->setShowToolbar(false)     
          ->build();
    }

  private function buildActivityTable(Customer $customer): array
  {
    # TODO: design timeline events (maybe cache an activity_log)
    #       including (i) new orders, (ii) payments, (iii) credit status changes, etc

    $changes = $this->audit->getEntityHistory('Customer', $customer->getId());

    $rows = [];
    foreach ($changes as $c) {
      $row = TableRow::create([
        'request_id' => $c->getRequestId(),
        'user_id' => $c->getUserId(),
        'changed_at' => $c->getChangedAt(),
      ])
      ->setId($c->getId());

      $rows[] = $row;
    }

    return TableView::create('customer_recent_activity')
      ->addField(
        TableField::text('request_id', 'Request')
              )
      ->addField(
        TableField::text('user_id', 'Changed By')
              )
      ->addField(
        TableField::date('changed_at', 'Changed At')
              )
      ->setRows($rows)
      ->setShowToolbar(false)
      ->setEmptyState('No recent activity')
      ->build();
  }
}
