<?php

namespace App\Katzen\Service\Utility;

use App\Katzen\Attribute\DashboardLayout;
use App\Katzen\Service\Utility\AlertService;
use App\Katzen\Service\Utility\EncouragementEngine;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DashboardContextService
{
    public function __construct(
      private Security $security,
      private RequestStack $requestStack,
      private AlertService $alertService,
      private EncouragementEngine $encouragementEngine,
      private UrlGeneratorInterface $urlGenerator,
#        private TaskRepository $taskRepo,
#        private NotificationRepository $notificationRepo
#        private OrderRepository $orderRepo,
#        private StockRepository $stockRepo,
    ) {}

  /**
   * Check if user is authenticated, redirect to login if not
   * 
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  private function ensureAuthenticated(): void
  {
    if (!$this->security->getUser()) {
      $request = $this->requestStack->getCurrentRequest();
      $currentUrl = $request ? $request->getUri() : null;
      
      $loginUrl = $this->urlGenerator->generate('app_login');
      if ($currentUrl) {
        $loginUrl .= '?_target_path=' . urlencode($currentUrl);
      }
      
      throw new \Symfony\Component\HttpKernel\Exception\HttpException(
        302,
        'Authentication required',
        null,
        ['Location' => $loginUrl]
      );
    }
  }
  
  public function getBaseContext(): array
  {        
    $this->ensureAuthenticated();
    $user = $this->security->getUser();
    
    if (!$user) {
      return [
        'user' => $this->buildUserContext(null),
        'encouragement' => ['header_message' => 'Please log in to continue'],
        'alerts' => [],
        'notifications' => [],
        'requires_authentication' => true,
      ];
    }
    
    $request = $this->requestStack->getCurrentRequest();
    $route = $request?->attributes->get('_route', 'unknown');
    
    return [
      'user' => $this->buildUserContext($user),
      'encouragement' => $this->generateEncouragement($user, $route),
      'alerts' => $this->getAlertBanner($user),
      'notifications' => $this->getNotifications($user),
    ];
  }

  private function buildUserContext($user): array
  {
    return $user ? [
      'firstName' => explode(" ", $user->getName())[0],
      'lastName' => explode(" ", $user->getName())[1],
      'role' => 'Cook',
      'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg',
    ] : [
      'firstName' => 'Curious',
      'lastName' => 'Cat',
      'role' => 'Guest',
      'profileImage' => 'https://cdn.freecodecamp.org/curriculum/cat-photo-app/relaxing-cat.jpg',
    ];
  }

  private function generateEncouragement($user, string $route): array
  {
    $context = [
      'route' => $route,
      'time' => new \DateTime(),
      'day' => new \DateTime(),
      'user_name' => explode(" ", $user?->getName())[0] ?? 'friend',
    ];

    // $context['open_orders'] = $this->orderRepo->countOpen();
    // $context['orders_completed_today'] = $this->orderRepo->countCompletedToday();
    // $context['low_stock_items'] = $this->stockRepo->countLowStock();
    // $context['is_first_login_today'] = $this->isFirstLoginToday($user);
    
    // mock data
    $context['open_orders'] = 3;
    $context['orders_completed_today'] = 7;
    $context['low_stock_items'] = 2;
    $context['is_first_login_today'] = false;
    $context['recent_errors'] = 0;

    $result = $this->encouragementEngine->generate($context);

    return $result;

    return match ($route) {
      'menu_create'      => ['header_message' => 'Build something delicious!'],
      'dashboard_home'   => ['header_message' => 'Ready to crush it?'],
      default            => ['header_message' => 'Keep going, you got this!'],
    };
  }

    private function getAlertBanner($user): array
    {
      $alerts = $this->alertService->getAlertsForContext(
        user: $user,
        route: $this->requestStack->getCurrentRequest()
            ?->attributes->get('_route', 'unknown'),
        routeParams: $this->requestStack->getCurrentRequest()
            ?->attributes->get('_route_params', [])
      );

      // TODO: design behavior around multiple alert state
      return $alerts[0] ?? [];
    }

    private function getNotifications($user): array
    {
        // TODO: design notification service
        return [0, 1, 2];
    }

    private function isFirstLoginToday($user): bool
    {
        // TODO: check last login timestamp
        return false;
    }

  private function buildLayoutContext(string $context, string $section, ?string $item = null): array
  {
    $layouts = [
      'service' => [
        'label' => 'Service',
        'template' => 'katzen/_dashboard_base.html.twig',
        'sections' => [
          [
            'key' => 'service-dashboard',
            'label' => 'Dashboard',
            'icon' => 'columns-gap',
            'route' => 'service_dashboard',
          ],              
          [            
            'key' => 'customer',
            'label' => 'Customers',
            'icon' => 'people',
            'items' => [
              ['key' => 'customer-create', 'label' => 'Add Customer', 'route' => 'customer_create'],
              ['key' => 'customer-panel', 'label' => 'Customer Panel', 'route' => 'customer_index'],              
              ['key' => 'customer-table', 'label' => 'Manage Customers', 'route' => 'customer_table'],
            ]
          ],
          [
            'key' => 'order',
            'label' => 'Orders',
            'icon' => 'cart',
            'items' => [
              ['key' => 'order-create', 'label' => 'Create Order', 'route' => 'order_create'],
              ['key' => 'order-panel', 'label' => 'Open Orders', 'route' => 'order_index'],
              ['key' => 'order-table', 'label' => 'Manage Orders', 'route' => 'order_table'],
            ]
          ],
          [
            'key' => 'menu-index',
            'label' => 'Menu',
            'icon' => 'fork-knife',
            'route' => 'menu_index', # TODO: "View Current Menu" page
          ],
          [
            'key' => 'stock',
            'label' => 'Stock',
            'icon' => 'boxes',
            'route' => 'stock_index'
          ],
        ],
      ],
    'kitchen' => [
      'label' => 'Prep',      
      'template' => 'katzen/_dashboard_base.html.twig',
      'sections' => [
        [
          'key' => 'menu',
          'label' => 'Menus',
          'icon' => 'fork-knife',
          'items' => [
            ['key' => 'menu-create', 'label' => 'Add Menu', 'route' => 'menu_create'],
            ['key' => 'menu-table', 'label' => 'Manage Menus', 'route' => 'menu_table'],
          ],
        ],
        [
          'key' => 'recipe',
          'label' => 'Recipes',
          'icon' => 'egg',
          'items' => [
            ['key' => 'recipe-create', 'label' => 'Add Recipe', 'route' => 'recipe_create'],
            ['key' => 'recipe-table', 'label' => 'Manage Recipes', 'route' => 'recipe_table'],
          ],
        ],        
      ],
    ],
    'supply' => [
      'label' => 'Supply',
      'template' => 'katzen/_dashboard_base.html.twig',
      'sections' => [
        [
          'key' => 'stock-index',
          'label' => 'Dashboard',
          'icon' => 'columns-gap',
          'route' => 'stock_index',
        ],
        [            
          'key' => 'vendor',
          'label' => 'Vendors',
          'icon' => 'building',
          'items' => [
            ['key' => 'vendor-create', 'label' => 'Add Vendor', 'route' => 'vendor_create'],
            ['key' => 'vendor-list', 'label' => 'Manage Vendors', 'route' => 'vendor_index'],
          ]
        ],
        [
          'key' => 'purchase',
          'label' => 'Purchases',
          'icon' => 'cart',
          'items' => [
            ['key' => 'purchase-create', 'label' => 'Create Purchase', 'route' => 'purchase_create'],
            ['key' => 'purchase-table', 'label' => 'Manage Purchases', 'route' => 'purchase_index']
          ]
        ],
        [
          'key' => 'receipt',
          'label' => 'Receipts',
          'icon' => 'inbox',
          'items' => [
            ['key' => 'receipt-table', 'label' => 'Manage Receipts', 'route' => 'receipt_index'],
            ['key' => 'receipt-create', 'label' => 'Receive Items', 'route' => 'receipt_create'],
          ]
        ],
        [
          'key' => 'stock',
          'label' => 'Stock',
          'icon' => 'boxes',
          'items' => [
            ['key' => 'stock-panel', 'label' => 'Current Stock', 'route' => 'stock_index'],              
            ['key' => 'stock-table', 'label' => 'Manage Stock', 'route' => 'stock_table'],
            ['key' => 'count-create', 'label' => 'Start Count', 'route' => 'stock_count_create'],
          ]
        ],
        [
          'key' => 'location',
          'label' => 'Locations',
          'icon' => 'geo-alt',
          'items' => [
            ['key' => 'location-create', 'label' => 'Add Location', 'route' => 'location_create'],
            ['key' => 'location-table', 'label' => 'Manage Locations', 'route' => 'location_index'],            
          ]
        ],
      ],
    ],
  'finance' => [
    'label' => 'Finance',
    'template' => 'katzen/_dashboard_base.html.twig',
    'sections' => [
      [
        'key' => 'customer',
        'label' => 'Customers',
        'icon' => 'people',
        'items' => [
          ['key' => 'customer-panel', 'label' => 'All Customers', 'route' => 'customer_index'],
          ['key' => 'customer-table', 'label' => 'Manage Customers', 'route' => 'customer_table'],          
        ]
      ],
      [
        'key' => 'invoice',
        'label' => 'Invoices',
        'icon' => 'envelope',
        'items' => [
          ['key' => 'invoice-create', 'label' => 'Create Invoice', 'route' => 'invoice_create'],
          ['key' => 'invoice-table', 'label' => 'Manage Invoices', 'route' => 'invoice_index'],
        ]
      ],
      [
        'key' => 'payment',
        'label' => 'Payments',
        'icon' => 'cash',
        'items' => [
          ['key' => 'payment-table', 'label' => 'All Payments', 'route' => 'payment_table'],
        ]
      ],
      [
        'key' => 'costing',
        'label' => 'Costing',
        'icon' => 'currency',
        'items' => [
          ['key' => 'costing-dashboard', 'label' => 'Costs Dashboard', 'route' => 'costing_dashboard'],
          ['key' => 'price-alerts', 'label' => 'Manage Price Alerts', 'route' => 'costing_price_alerts'],
          ['key' => 'alert-create', 'label' => 'Create Price Alert', 'route' => 'costing_alert_create'],
        ]
      ],
      [
        'key' => 'vendor-invoice',
        'label' => 'Vendor Invoices',
        'icon' => 'envelope',
        'items' => [
          ['key' => 'vendor-invoice-create', 'label' => 'Create Vendor Invoice', 'route' => 'vendor_invoice_create'],
          ['key' => 'vendor-invoice-table', 'label' => 'Manage Vendor Invoices', 'route' => 'vendor_invoice_index'],
        ]
      ],
    ],
  ]
  ];
    
    $layoutConfig = $layouts[$context] ?? null;
    
    if (!$layoutConfig) {
      return [];
    }
    
    return [
      'activeDash' => $layoutConfig['template'],
      'activeMenu' => $section,
      'activeItem' => $item,
      'layout' => $layoutConfig,
    ];
  }

  public function with(array $data = []): array
  {
    $base = $this->getBaseContext();
    
    // Controller override
    if (isset($data['layout'])) {
      $layoutData = $this->buildLayoutContext(
        $data['layout']['context'],
        $data['layout']['section'],
        $data['layout']['item'] ?? null
      );
      unset($data['layout']);
    } 
    else {
      $request = $this->requestStack->getCurrentRequest();
      
      if ($request) {
        $controller = $request->attributes->get('_controller');
        
        if ($controller && str_contains($controller, '::')) {
          [$class, $method] = explode('::', $controller);
          
          try {
            $reflection = new \ReflectionMethod($class, $method);
            $attributes = $reflection->getAttributes('App\Katzen\Attribute\DashboardLayout');
            
            if (!empty($attributes)) {
              $layoutAttr = $attributes[0]->newInstance();
              $layoutData = $this->buildLayoutContext(
                $layoutAttr->context,
                $layoutAttr->section,
                $layoutAttr->item
              );
            }
          } catch (\ReflectionException $e) {
            // no DashboardLayout attribute, no problem
            // (but pls add a DashboardLayout to your Route)
          }
        }
      }
    }
    
    if (isset($layoutData)) {
      $base = array_merge($base, $layoutData);
    }

    return array_merge($base, $data);
  }
}
