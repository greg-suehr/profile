<?php

namespace App\Katzen\Controller;

use App\Katzen\Service\Audit\AuditService;
use App\Katzen\Service\Utility\DashboardContextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for viewing audit logs and change history
 */
final class AuditController extends AbstractController
{
    public function __construct(
        private DashboardContextService $dashboardContext,
        private AuditService $audit,
    ) {}

    /**
     * View complete history for a specific entity
     */
    #[Route('/audit/entity/{type}/{id}', name: 'audit_entity_history')]
    public function entityHistory(
        string $type,
        string $id,
        Request $request
    ): Response {
        $limit = (int) $request->query->get('limit', 50);
        $history = $this->audit->getEntityHistory($type, $id, $limit);

        return $this->render('katzen/audit/entity_history.html.twig', 
            $this->dashboardContext->with([
                'activeItem' => 'change_log',
                'activeMenu' => 'audit',
                'entityType' => $type,
                'entityId' => $id,
                'history' => $history,
                'changeCount' => count($history),
            ])
        );
    }

    /**
     * View activity for a specific user
     */
    #[Route('/audit/user/{userId}', name: 'audit_user_activity')]
    public function userActivity(int $userId, Request $request): Response
    {
        $limit = (int) $request->query->get('limit', 100);
        $activity = $this->audit->getUserActivity($userId, $limit);

        return $this->render('katzen/audit/user_activity.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'userId' => $userId,
                'activity' => $activity,
            ])
        );
    }

    /**
     * View all changes within a specific request (bulk operation)
     */
    #[Route('/audit/request/{requestId}', name: 'audit_request_changes')]
    public function requestChanges(string $requestId): Response
    {
        $changes = $this->audit->getRequestChanges($requestId);

        return $this->render('katzen/audit/request_changes.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'requestId' => $requestId,
                'changes' => $changes,
                'changeCount' => count($changes),
            ])
        );
    }

    /**
     * Activity summary dashboard
     */
    #[Route('/audit/summary', name: 'audit_summary')]
    public function summary(Request $request): Response
    {
        $days = (int) $request->query->get('days', 7);
        $since = new \DateTime("-{$days} days");
        
        $summary = $this->audit->getActivitySummary($since);

        return $this->render('katzen/audit/summary.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'summary' => $summary,
                'days' => $days,
                'since' => $since,
            ])
        );
    }

    /**
     * View changes within a date range
     */
    #[Route('/audit/timeline', name: 'audit_timeline')]
    public function timeline(Request $request): Response
    {
        $start = new \DateTime($request->query->get('start', '-7 days'));
        $end = new \DateTime($request->query->get('end', 'now'));
        $entityType = $request->query->get('entity_type');
        $userId = $request->query->get('user_id') 
            ? (int) $request->query->get('user_id') 
            : null;

        $changes = $this->audit->getChangesBetween($start, $end, $entityType, $userId);

        return $this->render('katzen/audit/timeline.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'changes' => $changes,
                'start' => $start,
                'end' => $end,
                'entityType' => $entityType,
                'userId' => $userId,
            ])
        );
    }

    /**
     * Reconstruct and view entity state at a specific point in time
     */
    #[Route('/audit/reconstruct/{type}/{id}', name: 'audit_reconstruct')]
    public function reconstruct(
        string $type,
        string $id,
        Request $request
    ): Response {
        $asOfDate = $request->query->get('as_of', 'now');
        $asOf = new \DateTime($asOfDate);
        
        $state = $this->audit->reconstructStateAt($type, $id, $asOf);

        if (!$state) {
            $this->addFlash('warning', 'No historical data found for this entity.');
            return $this->redirectToRoute('audit_entity_history', [
                'type' => $type,
                'id' => $id,
            ]);
        }

        return $this->render('katzen/audit/reconstruct.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'entityType' => $type,
                'entityId' => $id,
                'asOf' => $asOf,
                'state' => $state,
            ])
        );
    }

    /**
     * Get field-level history
     */
    #[Route('/audit/field/{type}/{id}/{field}', name: 'audit_field_history')]
    public function fieldHistory(string $type, string $id, string $field): Response
    {
        $history = $this->audit->getFieldHistory($type, $id, $field);

        return $this->render('katzen/audit/field_history.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',              
                'activeMenu' => 'audit',
                'entityType' => $type,
                'entityId' => $id,
                'fieldName' => $field,
                'history' => $history,
            ])
        );
    }

    /**
     * Main audit log index/search page
     */
    #[Route('/audit', name: 'audit_index')]
    public function index(Request $request): Response
    {
        // Build search form data
        $entityType = $request->query->get('entity_type');
        $userId = $request->query->get('user_id');
        $startDate = $request->query->get('start_date', '-7 days');
        $endDate = $request->query->get('end_date', 'now');

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $changes = $this->audit->getChangesBetween(
            $start, 
            $end, 
            $entityType, 
            $userId ? (int)$userId : null
        );

        return $this->render('katzen/audit/index.html.twig',
            $this->dashboardContext->with([
                'activeItem' => 'change_log',      
                'activeMenu' => 'audit',
                'changes' => $changes,
                'filters' => [
                    'entity_type' => $entityType,
                    'user_id' => $userId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ])
        );
    }
}
