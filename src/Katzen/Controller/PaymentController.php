<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Customer;
use App\Katzen\Entity\Invoice;
use App\Katzen\Entity\Payment;
use App\Katzen\Form\PaymentType;
use App\Katzen\Repository\CustomerRepository;
use App\Katzen\Repository\InvoiceRepository;
use App\Katzen\Repository\PaymentRepository;
use App\Katzen\Service\AccountingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private PaymentRepository $paymentRepo,
        private InvoiceRepository $invoiceRepo,
        private CustomerRepository $customerRepo,
        private AccountingService $accountingService,
    ) {}

    #[Route('/payments', name: 'payment_index')]
    public function index(Request $request): Response
    {
        $payments = $this->paymentRepo->findBy([], ['payment_date' => 'DESC'], 100);

        $rows = [];
        foreach ($payments as $payment) {
            $row = TableRow::create([
                'payment_number' => $payment->getPaymentNumber(),
                'customer' => $payment->getCustomer()->getName(),
                'invoice' => $payment->getInvoice()?->getInvoiceNumber() ?? 'N/A',
                'amount' => '$' . number_format((float)$payment->getAmount(), 2),
                'method' => ucfirst(str_replace('_', ' ', $payment->getPaymentMethod())),
                'payment_date' => $payment->getPaymentDate()->format('Y-m-d'),
                'status' => $payment->getStatus(),
            ])
                ->setId($payment->getId())
                ->setLink('payment_show', ['id' => $payment->getId()]);

            if ($payment->getStatus() === 'failed') {
                $row->setStyleClass('table-danger');
            } elseif ($payment->getStatus() === 'completed') {
                $row->setStyleClass('table-success');
            }

            $rows[] = $row;
        }

        $table = TableView::create('payments-table')
            ->addField(TableField::link('payment_number', 'Payment #', 'payment_show')->sortable())
            ->addField(TableField::text('customer', 'Customer')->sortable())
            ->addField(TableField::text('invoice', 'Invoice')->hiddenMobile())
            ->addField(TableField::text('amount', 'Amount')->align('right')->sortable())
            ->addField(TableField::text('method', 'Method')->hiddenMobile())
            ->addField(TableField::date('payment_date', 'Date', 'Y-m-d')->sortable())
            ->addField(
                TableField::badge('status', 'Status')
                    ->badgeMap([
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'secondary',
                    ])
                    ->sortable()
            )
            ->setRows($rows)
            ->addQuickAction(TableAction::view('payment_show'))
            ->setSearchPlaceholder('Search by payment number, customer...')
            ->setEmptyState('No payments recorded.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'activeItem' => 'payments',
            'activeMenu' => 'accounting',
            'table' => $table,
        ]));
    }

    #[Route('/payment/create', name: 'payment_create')]
    public function create(Request $request): Response
    {
        $invoiceId = $request->query->get('invoice_id');
        $customerId = $request->query->get('customer_id');

        $invoice = $invoiceId ? $this->invoiceRepo->find($invoiceId) : null;
        $customer = $customerId ? $this->customerRepo->find($customerId) : null;

        if ($invoice) {
            $customer = $invoice->getCustomer();
        }

        $payment = new Payment();
        if ($customer) {
            $payment->setCustomer($customer);
        }
        if ($invoice) {
            $payment->setInvoice($invoice);
            $payment->setAmount($invoice->getAmountDue());
        }

        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->accountingService->recordPayment(
                customer: $payment->getCustomer(),
                amount: (float)$payment->getAmount(),
                paymentMethod: $payment->getPaymentMethod(),
                invoice: $payment->getInvoice(),
                options: [
                    'payment_date' => $payment->getPaymentDate(),
                    'transaction_reference' => $payment->getTransactionReference(),
                    'notes' => $payment->getNotes(),
                ]
            );

            if ($result->isSuccess()) {
                $this->addFlash('success', 'Payment recorded successfully.');
                return $this->redirectToRoute('payment_show', ['id' => $result->getData()['payment_id']]);
            } else {
                $this->addFlash('danger', $result->getMessage());
            }
        }

        return $this->render('katzen/payment/form.html.twig', $this->dashboardContext->with([
            'activeItem' => 'payments',
            'activeMenu' => 'accounting',
            'form' => $form->createView(),
            'payment' => null,
            'invoice' => $invoice,
        ]));
    }

    #[Route('/payment/{id}', name: 'payment_show')]
    public function show(Request $request, Payment $payment): Response
    {
        return $this->render('katzen/payment/show.html.twig', $this->dashboardContext->with([
            'activeItem' => 'payments',
            'activeMenu' => 'accounting',
            'payment' => $payment,
        ]));
    }
}
