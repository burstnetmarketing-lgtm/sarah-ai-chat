<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\UsageLogRepository;

/**
 * Read-only usage reporting endpoints.
 *
 * All endpoints require manage_options (WordPress admin).
 *
 * GET /usage               — paginated usage records with optional filters
 * GET /usage/summary       — aggregate totals for the same filter scope
 *
 * Supported query params (both endpoints):
 *   tenant_id, site_id, session_id, agent_id, date_from (YYYY-MM-DD), date_to (YYYY-MM-DD)
 *
 * GET /usage also accepts:
 *   limit (default 50, max 200), offset (default 0)
 */
class UsageController
{
    private UsageLogRepository $repo;

    public function __construct()
    {
        $this->repo = new UsageLogRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/usage/summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'summary'],
            'permission_callback' => [$this, 'can'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/usage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'can'],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * GET /usage
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        [$tenantId, $siteId, $sessionId, $agentId, $dateFrom, $dateTo] = $this->parseFilters($request);

        $limit  = min((int) ($request->get_param('limit')  ?? 50), 200);
        $offset = max((int) ($request->get_param('offset') ?? 0), 0);

        $rows = $this->repo->findByFilters($tenantId, $siteId, $sessionId, $agentId, $dateFrom, $dateTo, $limit, $offset);

        $data = array_map([$this, 'formatRow'], $rows);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['limit' => $limit, 'offset' => $offset, 'count' => count($data)],
        ], 200);
    }

    /**
     * GET /usage/summary
     */
    public function summary(\WP_REST_Request $request): \WP_REST_Response
    {
        [$tenantId, $siteId, , , $dateFrom, $dateTo] = $this->parseFilters($request);

        $summary = $this->repo->getSummary($tenantId, $siteId, $dateFrom, $dateTo);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $summary,
        ], 200);
    }

    /**
     * Extract and sanitise common filter params from the request.
     *
     * @return array{?int, ?int, ?int, ?int, ?string, ?string}
     */
    private function parseFilters(\WP_REST_Request $request): array
    {
        $tenantId  = ($request->get_param('tenant_id')  !== null) ? (int) $request->get_param('tenant_id')  : null;
        $siteId    = ($request->get_param('site_id')    !== null) ? (int) $request->get_param('site_id')    : null;
        $sessionId = ($request->get_param('session_id') !== null) ? (int) $request->get_param('session_id') : null;
        $agentId   = ($request->get_param('agent_id')   !== null) ? (int) $request->get_param('agent_id')   : null;
        $dateFrom  = $this->sanitiseDate($request->get_param('date_from'));
        $dateTo    = $this->sanitiseDate($request->get_param('date_to'));

        return [$tenantId, $siteId, $sessionId, $agentId, $dateFrom, $dateTo];
    }

    private function sanitiseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        // Accept YYYY-MM-DD only
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function formatRow(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'tenant_id'       => $row['tenant_id']       ? (int) $row['tenant_id']       : null,
            'site_id'         => $row['site_id']         ? (int) $row['site_id']         : null,
            'agent_id'        => $row['agent_id']        ? (int) $row['agent_id']        : null,
            'subscription_id' => $row['subscription_id'] ? (int) $row['subscription_id'] : null,
            'session_id'      => $row['session_id']      ? (int) $row['session_id']      : null,
            'event_type'      => $row['event_type'],
            'tokens_in'       => $row['tokens_in']  !== null ? (int) $row['tokens_in']  : null,
            'tokens_out'      => $row['tokens_out'] !== null ? (int) $row['tokens_out'] : null,
            'meta'            => $row['meta'] ? json_decode($row['meta'], true) : null,
            'created_at'      => $row['created_at'],
        ];
    }
}
