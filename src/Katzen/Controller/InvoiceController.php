<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Invoice;
use App\Katzen\Entity\Order;
use App\Katzen\Entity\Payment;
use App\Katzen\Form\InvoiceType;
use App\Katzen\Form\PaymentType;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Repository\InvoiceRepository;
use App\Katzen\Repository\OrderRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvoiceController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private InvoiceRepository $invoiceRepo,
        private OrderRepository $orderRepo,
        private CustomerRepository $customerRepo,
        private AccountingService $accountingService,
    ) {}

  #[Route('/invoices', name: 'invoice_index')]
  #[DashboardLayout('finance', 'invoice', 'invoice-table')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $invoices = $status === 'all'
            ? $this->invoiceRepo->findBy([], ['invoice_date' => 'DESC'])
            : $this->invoiceRepo->findBy(['status' => $status], ['invoice_date' => 'DESC']);

        $rows = [];
        foreach ($invoices as $invoice) {
            $row = TableRow::create([
                'invoice_number' => $invoice->getInvoiceNumber(),
                'customer' => $invoice->getCustomer()->getName(),
                'invoice_date' => $invoice->getInvoiceDate()->format('Y-m-d'),
                'due_date' => $invoice->getDueDate()->format('Y-m-d'),
                'total' => '$' . number_format((float)$invoice->getTotalAmount(), 2),
                'amount_due' => '$' . number_format((float)$invoice->getAmountDue(), 2),
                'status' => $invoice->getStatus(),
            ])
                ->setId($invoice->getId())
                ->setLink('invoice_show', ['id' => $invoice->getId()]);

            if ($invoice->getStatus() === 'overdue') {
                $row->setStyleClass('table-danger');
            } elseif ($invoice->getStatus() === 'paid') {
                $row->setStyleClass('table-success');
            }

            $rows[] = $row;
        }

        $table = TableView::create('invoices-table')
            ->addField(TableField::link('invoice_number', 'Invoice #', 'invoice_show')->sortable())
            ->addField(TableField::text('customer', 'Customer')->sortable())
            ->addField(TableField::date('invoice_date', 'Invoice Date', 'Y-m-d')->sortable())
            ->addField(TableField::date('due_date', 'Due Date', 'Y-m-d')->sortable()->hiddenMobile())
            ->addField(TableField::text('total', 'Total')->align('right')->sortable())
            ->addField(TableField::text('amount_due', 'Amount Due')->align('right')->sortable())
            ->addField(
                TableField::badge('status', 'Status')
                    ->badgeMap([
                        'draft' => 'secondary',
                        'sent' => 'info',
                        'partial' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'dark',
                    ])
                    ->sortable()
            )
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(TableAction::view('invoice_show'))
            ->addQuickAction(
                TableAction::create('record_payment', 'Record Payment')
                    ->setIcon('bi-cash-coin')
                    ->setVariant('outline-success')
                    ->setRoute('payment_create')
            )
            ->setSearchPlaceholder('Search by invoice number, customer...')
            ->setEmptyState('No invoices found.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'table' => $table,
            'bulkRoute' => 'invoice_bulk',
            'csrfSlug' => 'invoice_bulk',
        ]));
    }

  #[Route('/invoice/create', name: 'invoice_create')]
  #[DashboardLayout('finance', 'invoice', 'invoice-create')]  
    public function create(Request $request): Response
    {
        $orderId = $request->query->get('order_id');
        $order = $orderId ? $this->orderRepo->find($orderId) : null;

        if ($order && !$order->getCustomerEntity()) {
            $this->addFlash('warning', 'Order must have a customer assigned before creating invoice.');
            return $this->redirectToRoute('order_index');
        }

        $invoice = new Invoice();
        if ($order) {
            $result = $this->accountingService->createInvoiceFromOrder($order);
            
            if ($result->isSuccess()) {
                $this->addFlash('success', 'Invoice created from order.');
                return $this->redirectToRoute('invoice_show', ['id' => $result->getData()['invoice_id']]);
            } else {
                $this->addFlash('danger', $result->getMessage());
                return $this->redirectToRoute('order_index');
            }
        }

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->calculateTotals();
            $this->invoiceRepo->save($invoice);
            $this->addFlash('success', 'Invoice created successfully.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('katzen/invoice/create_invoice.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'invoice' => null,
        ]));
    }

  #[Route('/invoice/{id}', name: 'invoice_show')]
  #[DashboardLayout('finance', 'invoice', 'invoice-show')]  
  public function show(Request $request, Invoice $invoice): Response
  {
        return $this->render('katzen/invoice/show.html.twig', $this->dashboardContext->with([
          'invoice' => $invoice,
        ]));
    }

  #[Route('/invoice/edit/{id}', name: 'invoice_edit')]
  #[DashboardLayout('finance', 'invoice', 'invoice-create')]  
    public function edit(Request $request, Invoice $invoice): Response
    {
        if (in_array($invoice->getStatus(), ['paid', 'cancelled'], true)) {
            $this->addFlash('warning', 'Cannot edit paid or cancelled invoices.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $form = $this->createForm(InvoiceType::class, $invoice);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->calculateTotals();
            $this->invoiceRepo->save($invoice);
            $this->addFlash('success', 'Invoice updated successfully.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('katzen/invoice/form.html.twig', $this->dashboardContext->with([
          'form' => $form->createView(),
          'invoice' => $invoice,
        ]));
    }

    #[Route('/invoice/{id}/send', name: 'invoice_send', methods: ['POST'])]
    public function send(Request $request, Invoice $invoice): Response
    {
        if (!$this->isCsrfTokenValid('invoice_send_' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        if ($invoice->getStatus() === 'draft') {
            $invoice->setStatus('sent');
            $this->invoiceRepo->save($invoice);
            
            // TODO: Implement email sending
            $this->addFlash('success', 'Invoice marked as sent.');
        } else {
            $this->addFlash('info', 'Invoice has already been sent.');
        }

        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }

  #[Route('/invoice/{id}/cancel', name: 'invoice_cancel', methods: ['POST'])]
  #[DashboardLayout('finance', 'invoice', 'invoice-cancel')]  
    public function cancel(Request $request, Invoice $invoice): Response
    {
        if (!$this->isCsrfTokenValid('invoice_cancel_' . $invoice->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        if ($invoice->getStatus() === 'paid') {
            $this->addFlash('warning', 'Cannot cancel paid invoices.');
            return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
        }

        $invoice->setStatus('cancelled');
        $this->invoiceRepo->save($invoice);
        $this->addFlash('success', 'Invoice cancelled.');

        return $this->redirectToRoute('invoice_index');
    }

    #[Route('/invoices/bulk', name: 'invoice_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('invoice_bulk', $payload['_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
        }

        $action = $payload['action'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? []);

        if (!$action || empty($ids)) {
            return $this->json(['ok' => false, 'error' => 'Missing action or ids'], 400);
        }

        switch ($action) {
            case 'send':
                foreach ($ids as $id) {
                    $invoice = $this->invoiceRepo->find($id);
                    if ($invoice && $invoice->getStatus() === 'draft') {
                        $invoice->setStatus('sent');
                    }
                }
                $this->invoiceRepo->flush();
                break;

            case 'cancel':
                foreach ($ids as $id) {
                    $invoice = $this->invoiceRepo->find($id);
                    if ($invoice && $invoice->getStatus() !== 'paid') {
                        $invoice->setStatus('cancelled');
                    }
                }
                $this->invoiceRepo->flush();
                break;

            default:
                return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
        }

        return $this->json(['ok' => true]);
    }
}
