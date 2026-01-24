<?php

namespace App\Katzen\Controller;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Component\TableView\TableField;
use App\Katzen\Component\TableView\TableRow;
use App\Katzen\Component\TableView\TableView;
use App\Katzen\Component\TableView\TableAction;
use App\Katzen\Entity\PriceRule;
use App\Katzen\Form\PriceRuleType;
use App\Katzen\Repository\PriceRuleRepository;
use App\Katzen\Service\Utility\DashboardContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '{domain}', requirements: ['domain' => '%katzen_hosts%'], defaults: ['domain' => 'getkatzen.com'])]
final class PriceRuleController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private PriceRuleRepository $priceRuleRepo,
        private EntityManagerInterface $em,
    ) {}
    
    #[Route('/price-rules', name: 'price_rule_index')]
    #[DashboardLayout('catalog', 'price-rule', 'price-rule-table')]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status', 'active');
        $typeFilter = $request->query->get('type');
        $search = $request->query->get('search');

        $qb = $this->priceRuleRepo->createQueryBuilder('pr')
            ->orderBy('pr.priority', 'ASC')
            ->addOrderBy('pr.id', 'DESC');

        if ($statusFilter === 'active') {
            $qb->andWhere('pr.status = :active')
               ->setParameter('active', 'active');
        } elseif ($statusFilter === 'inactive') {
            $qb->andWhere('pr.status = :active')
               ->setParameter('active', 'inactive');
        }
        
        if ($typeFilter) {
          $qb->andWhere('pr.type = :type')
             ->setParameter('type', $typeFilter);
        }

        if ($search) {
            $qb->andWhere('pr.name LIKE :search OR pr.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $priceRules = $qb->getQuery()->getResult();

        $rows = [];
        foreach ($priceRules as $rule) {
            $row = TableRow::create([
                'priority' => $rule->getPriority(),
                'name' => $rule->getName(),
                'type' => $rule->getType(),
                'active' => $rule->isActive(),
                'stackable' => $rule->isStackable(),
                'exclusive' => $rule->isExclusive(),
                'applicable_count' => $rule->getApplicableSellables()->count(),
                'created_at' => $rule->getCreatedAt()?->format('Y-m-d') ?? 'N/A',
            ])
                ->setId($rule->getId())
                ->setLink('price_rule_show', ['id' => $rule->getId()]);

            if (!$rule->isActive()) {
                $row->setStyleClass('text-muted');
            }

            $rows[] = $row;
        }

        $table = TableView::create('price-rules-table')
            ->addField(
                TableField::amount('priority', 'Priority', 0)
                    ->sortable()
            )
            ->addField(
                TableField::link('name', 'Rule Name', 'price_rule_show')
                    ->sortable()
            )
            ->addField(
                TableField::badge('type', 'Type')
                    ->badgeMap([
                        'customer_segment' => 'info',
                        'volume_tier' => 'primary',
                        'promotion' => 'success',
                        'time_based' => 'warning',
                        'fixed_price' => 'secondary',
                    ])
                    ->sortable()
            )
            ->addField(
                TableField::text('stackable', 'Stackable')
                    ->hiddenMobile()
            )
            ->addField(
                TableField::text('exclusive', 'Exclusive')
                    ->hiddenMobile()
            )
            ->addField(
                TableField::amount('applicable_count', 'Items', 0)
                    ->hiddenMobile()
            )
            ->addField(
                TableField::date('created_at', 'Created', 'Y-m-d')
                    ->sortable()
                    ->hiddenMobile()
            )
            ->setRows($rows)
            ->addQuickAction(TableAction::edit('price_rule_edit'))
            ->addQuickAction(TableAction::view('price_rule_show'))
            ->setSelectable(true)
            ->setSearchPlaceholder('Search by item name, rule type, ...')
            ->setEmptyState('No price rules found.')
            ->build();
        
        return $this->render('katzen/component/table_view.html.twig', $this->dashboardContext->with([
            'table' => $table,
            'filters' => [
                'status' => $statusFilter,
                'type' => $typeFilter,
                'search' => $search,
            ],
            'bulkRoute' => 'price_rule_bulk',
            'csrfSlug' => 'price_rule_bulk',
        ]));
    }

    #[Route('/price-rule/create', name: 'price_rule_create')]
    #[DashboardLayout('catalog', 'price-rule', 'price-rule-create')]
    public function create(Request $request): Response
    {
        $priceRule = new PriceRule();
        $priceRule->setStatus('active');
        $priceRule->setPriority(100); // Default priority
        
        $form = $this->createForm(PriceRuleType::class, $priceRule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($priceRule);
            $this->em->flush();
            
            $this->addFlash('success', 'Price rule created successfully.');
            return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
        }


        return $this->render('katzen/price_rule/create_price_rule.html.twig', $this->dashboardContext->with([
          'form' => $form,
          'priceRule' => null,
        ]));
    }

    #[Route('/price-rule/{id}', name: 'price_rule_show', requirements: ['id' => '\d+'])]
    #[DashboardLayout('catalog', 'price-rule', 'price-rule-show')]
    public function show(Request $request, PriceRule $priceRule): Response
    {
        return $this->render('katzen/price_rule/show_price_rule.html.twig', $this->dashboardContext->with([
            'priceRule' => $priceRule,
        ]));
    }

    #[Route('/price-rule/edit/{id}', name: 'price_rule_edit')]
    #[DashboardLayout('catalog', 'price-rule', 'price-rule-edit')]
    public function edit(Request $request, PriceRule $priceRule): Response
    {
        $form = $this->createForm(PriceRuleType::class, $priceRule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Price rule updated successfully.');
            return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
        }

        return $this->render('katzen/price_rule/create_price_rule.html.twig', $this->dashboardContext->with([
            'form' => $form->createView(),
            'priceRule' => $priceRule,
        ]));
    }

    #[Route('/price-rule/{id}/toggle-active', name: 'price_rule_toggle_active', methods: ['POST'])]
    public function toggleActive(Request $request, PriceRule $priceRule): Response
    {
        if (!$this->isCsrfTokenValid('price_rule_toggle_' . $priceRule->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
        }

        $priceRule->setActive(!$priceRule->isActive());
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Price rule %s.',
            $priceRule->isActive() ? 'activated' : 'deactivated'
        ));

        return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
    }

    #[Route('/price-rule/{id}/duplicate', name: 'price_rule_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, PriceRule $priceRule): Response
    {
        if (!$this->isCsrfTokenValid('price_rule_duplicate_' . $priceRule->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
        }

        $newRule = clone $priceRule;
        $newRule->setName($priceRule->getName() . ' (Copy)');
        $newRule->setActive(false); // Start inactive for safety
        
        $this->em->persist($newRule);
        $this->em->flush();

        $this->addFlash('success', 'Price rule duplicated successfully.');
        return $this->redirectToRoute('price_rule_edit', ['id' => $newRule->getId()]);
    }

    #[Route('/price-rule/{id}/delete', name: 'price_rule_delete', methods: ['POST'])]
    public function delete(Request $request, PriceRule $priceRule): Response
    {
        if (!$this->isCsrfTokenValid('price_rule_delete_' . $priceRule->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('price_rule_show', ['id' => $priceRule->getId()]);
        }

        $this->em->remove($priceRule);
        $this->em->flush();

        $this->addFlash('success', 'Price rule deleted successfully.');
        return $this->redirectToRoute('price_rule_index');
    }
}
