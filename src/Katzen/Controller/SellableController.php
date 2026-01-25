<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\PanelView\PanelAction;
use App\Katzen\Component\PanelView\PanelCard;
use App\Katzen\Component\PanelView\PanelField;
use App\Katzen\Component\PanelView\PanelGroup;
use App\Katzen\Component\PanelView\PanelView;
use App\Katzen\Component\TableView\TableField;
use App\Katzen\Component\TableView\TableRow;
use App\Katzen\Component\TableView\TableView;
use App\Katzen\Entity\Sellable;
use App\Katzen\Entity\SellableVariant;
use App\Katzen\Entity\SellableComponent;
use App\Katzen\Entity\SellableModifierGroup;
use App\Katzen\Form\SellableType;
use App\Katzen\Form\SellableVariantType;
use App\Katzen\Repository\SellableRepository;
use App\Katzen\Repository\StockTargetRepository;
use App\Katzen\Service\Order\PricingService;
use App\Katzen\Service\Utility\DashboardContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(condition: "request.getHost() matches '%katzen_match%'")]
final class SellableController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private SellableRepository $sellableRepo,
        private StockTargetRepository $stockTargetRepo,
        private PricingService $pricingService,
        private EntityManagerInterface $em,
    ) {}
    
    #[Route('/sellables', name: 'sellable_index')]
    #[DashboardLayout('catalog', 'sellable', 'sellable-panel')]
    public function index(Request $request): Response
    {
        $activeGroup = $request->query->get('group');
        $q = $request->query->get('q');
        $sellables = $this->sellableRepo->findBy(['parent' => null], ['name' => 'ASC']);

        $cards = [];
        foreach ($sellables as $s) {
            $variantCount = $s->getVariants()->count();
            $modifierGroupCount = $s->getModifierGroups()->count();
            
            // Try to get a base price (first variant if available)
            $basePrice = 0.00;
            if ($variantCount > 0) {
                $firstVariant = $s->getVariants()->first();
                $basePrice = (float)($s->getBasePrice() ?? 0);
            }

            $data = [
                'id' => (string)$s->getId(),
                'name' => $s->getName(),
                'sku' => $s->getSku() ?? '-',
                'type' => $s->getType(),
                'category' => $s->getCategory() ?? 'Uncategorized',
                'base_price' => $basePrice,
                'variant_count' => $variantCount,
                'modifier_count' => $modifierGroupCount,
            ];

            $card = PanelCard::create($data['id'])
                ->setTitle($data['name'])
                ->setData($data)
                ->addBadge(strtoupper($data['type']), $this->getTypeBadgeColor($data['type']))
                ->setMeta($data['sku'])
                ->addPrimaryField(PanelField::currency('base_price', 'Base Price'))
                ->addPrimaryField(PanelField::number('variant_count', 'Variants', 0)->icon('bi-list'))
                ->addContextField(PanelField::text('category', 'Category')->muted())
                ->addQuickAction(PanelAction::view('sellable_show'))
                ->addQuickAction(PanelAction::edit('sellable_edit'))
              ;

            if ($modifierGroupCount > 0) {
                $card->addContextField(
                    PanelField::number('modifier_count', 'Modifier Groups', 0)->icon('bi-plus-circle')
                );
            }

            $cards[] = $card;
        }

        $groups = [
            PanelGroup::create('all', 'All Items')->setIcon('bi-grid-3x3'),
            PanelGroup::create('simple', 'Simple Items')->whereEquals('type', 'simple')->setIcon('bi-box'),
            PanelGroup::create('configurable', 'Configurable')->whereEquals('type', 'configurable')->setIcon('bi-sliders'),
            PanelGroup::create('bundle', 'Bundles')->whereEquals('type', 'bundle')->setIcon('bi-collection'),
            PanelGroup::create('modifier', 'Modifiers')->whereEquals('type', 'modifier')->setIcon('bi-plus-circle'),
        ];

        $panel = PanelView::create('sellables')
            ->setCards($cards)
            ->setSelectable(true)
            ->setSearchPlaceholder('Search by name, SKU, or category...')
            ->setEmptyState('No sellable items found. Create your first menu item to get started!');

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
            'groupSlug' => 'sellable_index',
        ]));
    }

    #[Route('/sellable/create', name: 'sellable_create')]
    #[DashboardLayout('catalog', 'sellable', 'sellable-create')]
    public function create(Request $request): Response
    {
        $sellable = new Sellable();
        $form = $this->createForm(SellableType::class, $sellable);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($sellable);
            $this->em->flush();
            
            $this->addFlash('success', 'Sellable item created successfully.');
            return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
        }

        return $this->render('katzen/sellable/create_sellable.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'sellable' => null,
        ]));
    }

    #[Route('/sellable/{id}', name: 'sellable_show', requirements: ['id' => '\d+'])]
    #[DashboardLayout('catalog', 'sellable', 'sellable-show')]
    public function show(Request $request, Sellable $sellable): Response
    {
        return $this->render('katzen/sellable/show_sellable.html.twig', $this->dashboardContext->with([
            'sellable' => $sellable,
        ]));
    }

    #[Route('/sellable/edit/{id}', name: 'sellable_edit')]
    #[DashboardLayout('catalog', 'sellable', 'sellable-edit')]
    public function edit(Request $request, Sellable $sellable): Response
    {
        $form = $this->createForm(SellableType::class, $sellable);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Sellable item updated successfully.');
            return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
        }

        return $this->render('katzen/sellable/create_sellable.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'sellable' => $sellable,
        ]));
    }

    #[Route('/sellable/{id}/variant/add', name: 'sellable_variant_add')]
    #[DashboardLayout('catalog', 'sellable', 'sellable-variant-add')]
    public function addVariant(Request $request, Sellable $sellable): Response
    {
        $variant = new SellableVariant();
        $variant->setSellable($sellable);
        
        $form = $this->createForm(SellableVariantType::class, $variant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($variant);
            $this->em->flush();
            
            $this->addFlash('success', 'Variant added successfully.');
            return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
        }

        return $this->render('katzen/sellable/add_variant.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'sellable' => $sellable,
            'variant' => null,
        ]));
    }

    #[Route('/sellable/{sellableId}/variant/edit/{variantId}', name: 'sellable_variant_edit')]
    #[DashboardLayout('catalog', 'sellable', 'sellable-variant-edit')]
    public function editVariant(Request $request, int $sellableId, int $variantId): Response
    {
        $sellable = $this->sellableRepo->find($sellableId);
        $variant = $this->em->getRepository(SellableVariant::class)->find($variantId);

        if (!$sellable || !$variant || $variant->getSellable()->getId() !== $sellable->getId()) {
            throw $this->createNotFoundException('Variant not found or does not belong to this sellable.');
        }

        $form = $this->createForm(SellableVariantType::class, $variant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Variant updated successfully.');
            return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
        }

        return $this->render('katzen/sellable/add_variant.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'sellable' => $sellable,
            'variant' => $variant,
        ]));
    }

    #[Route('/sellable/{id}/toggle-active', name: 'sellable_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, Sellable $sellable): Response
    {
        if (!$this->isCsrfTokenValid('sellable_toggle_' . $sellable->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
        }
        
        return $this->redirectToRoute('sellable_show', ['id' => $sellable->getId()]);
    }

    private function getTypeBadgeColor(string $type): string
    {
        return match($type) {
            'simple' => 'primary',
            'configurable' => 'info',
            'bundle' => 'success',
            'modifier' => 'warning',
            default => 'secondary',
        };
    }
}
