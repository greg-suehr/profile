<?php

namespace App\Katzen\Controller;

use App\Katzen\Component\TableView\{TableView, TableRow, TableField, TableAction};
use App\Katzen\Entity\Vendor;
use App\Katzen\Form\VendorType;
use App\Katzen\Repository\VendorRepository;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VendorController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private VendorRepository $vendorRepo,
    ) {}

    #[Route('/vendors', name: 'vendor_index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $vendors = $status === 'all'
            ? $this->vendorRepo->findBy([], ['name' => 'ASC'])
            : $this->vendorRepo->findBy(['status' => $status], ['name' => 'ASC']);

        $rows = [];
        foreach ($vendors as $vendor) {
            $rows[] = TableRow::create([
                'id' => $vendor->getId(),
                'name' => $vendor->getName(),
                'email' => $vendor->getEmail() ?? '—',
                'phone' => $vendor->getPhone() ?? '—',
                'status' => $vendor->getStatus(),
            ])
            ->setId($vendor->getId());
        }

        $table = TableView::create('Vendors')
            ->addField(
              TableField::text('name', 'Vendor Name')->sortable(),
            )
            ->addField(
              TableField::text('email', 'Email'),
            )
            ->addField(
              TableField::text('phone', 'Phone'),
            )
            ->addField(
              TableField::badge('status', 'Status')->sortable(),
            )          
            ->setRows($rows)
            ->setSelectable(true)
            ->addQuickAction(
                TableAction::create('view', 'View')
                    ->setIcon('bi bi-eye')
                    ->setVariant('outline-primary')
                    ->setRoute('vendor_show')                  
            )
            ->addQuickAction(
                TableAction::create('edit', 'Edit')
                    ->setIcon('bi bi-pencil')
                    ->setVariant('outline-secondary')
                    ->setRoute('vendor_edit')                  
            )
            ->setSearchPlaceholder('Search vendors by name, contact, or email...')
            ->setEmptyState('No vendors found.')
            ->build();

        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'vendor-list',
            'activeMenu' => 'vendor',
            'table' => $table,
            'bulkRoute' => 'vendor_bulk',
            'csrfSlug' => 'vendor_bulk',
        ]));
    }

    #[Route('/vendor/create', name: 'vendor_create')]
    public function create(Request $request): Response
    {
        $vendor = new Vendor();
        $form = $this->createForm(VendorType::class, $vendor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->vendorRepo->save($vendor);
            $this->addFlash('success', 'Vendor created successfully.');
            return $this->redirectToRoute('vendor_index');
        }

        return $this->render('katzen/vendor/create_vendor.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'vendor-create',
            'activeMenu' => 'vendor',
            'form' => $form->createView(),
            'vendor' => null,
        ]));
    }

    #[Route('/vendor/{id}', name: 'vendor_show')]
    public function show(Vendor $vendor): Response
    {
        return $this->render('katzen/vendor/show_vendor.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'vendor-view',
            'activeMenu' => 'vendor',
            'vendor' => $vendor,
        ]));
    }

    #[Route('/vendor/edit/{id}', name: 'vendor_edit')]
    public function edit(Request $request, Vendor $vendor): Response
    {
        $form = $this->createForm(VendorType::class, $vendor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->vendorRepo->save($vendor);
            $this->addFlash('success', 'Vendor updated successfully.');
            return $this->redirectToRoute('vendor_show', ['id' => $vendor->getId()]);
        }

        return $this->render('katzen/vendor/form.html.twig', $this->dashboardContext->with([
            'activeDash' => 'katzen/dash-supply.html.twig',
            'activeItem' => 'vendor-edit',
            'activeMenu' => 'vendor',
            'form' => $form->createView(),
            'vendor' => $vendor,
        ]));
    }

    #[Route('/vendors/bulk', name: 'vendor_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('vendor_bulk', $payload['_token'] ?? '')) {
            return $this->json(['ok' => false, 'error' => 'Bad CSRF'], 400);
        }

        $action = $payload['action'] ?? null;
        $ids = array_map('intval', $payload['ids'] ?? []);

        if (empty($ids)) {
            return $this->json(['ok' => false, 'error' => 'No vendors selected'], 400);
        }

        $vendors = $this->vendorRepo->findBy(['id' => $ids]);
        $count = count($vendors);

        switch ($action) {
            case 'activate':
                foreach ($vendors as $vendor) {
                    $vendor->setStatus('active');
                }
                $this->vendorRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count vendor(s) activated"]);

            case 'deactivate':
                foreach ($vendors as $vendor) {
                    $vendor->setStatus('inactive');
                }
                $this->vendorRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count vendor(s) deactivated"]);

            case 'delete':
                foreach ($vendors as $vendor) {
                    $this->vendorRepo->remove($vendor);
                }
                $this->vendorRepo->flush();
                return $this->json(['ok' => true, 'message' => "$count vendor(s) deleted"]);

            default:
                return $this->json(['ok' => false, 'error' => 'Unknown action'], 400);
        }
    }
}
