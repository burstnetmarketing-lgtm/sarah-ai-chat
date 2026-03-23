import React, { useState } from 'react';

const BASE = '/wp-json/sarah-ai-server/v1';

// ─── Data ────────────────────────────────────────────────────────────────────

const GROUPS = [
  {
    id: 'widget',
    label: 'Widget (Public)',
    badge: 'bg-success',
    description: 'Used by the sarah-ai-client widget. No WordPress login required — auth via account_key + site_key.',
    endpoints: [
      {
        method: 'POST',
        path: '/chat',
        summary: 'Send a message and receive an AI reply.',
        auth: 'account_key + site_key',
        params: [
          { name: 'account_key', type: 'string', req: true,  desc: 'Tenant account key' },
          { name: 'site_key',    type: 'string', req: true,  desc: 'Site key' },
          { name: 'message',     type: 'string', req: true,  desc: 'User message text' },
          { name: 'session_uuid',type: 'string', req: false, desc: 'Continue existing session' },
          { name: 'lead',        type: 'object', req: false, desc: '{name?, phone?, email?} — captured once per session' },
        ],
        response: '{ success, session_uuid, message, agent }',
      },
      {
        method: 'GET',
        path: '/chat/history',
        summary: 'Fetch ordered message history for an existing session (used on widget reload).',
        auth: 'account_key + site_key',
        params: [
          { name: 'account_key',  type: 'string', req: true, desc: 'Tenant account key' },
          { name: 'site_key',     type: 'string', req: true, desc: 'Site key' },
          { name: 'session_uuid', type: 'string', req: true, desc: 'Existing session UUID' },
        ],
        response: '{ success, session_uuid, messages: [{role, content}] }',
      },
      {
        method: 'GET',
        path: '/sites/{uuid}/knowledge-fields',
        summary: 'Returns merged public structured KB fields for a site (contact, business data).',
        auth: 'account_key + site_key',
        params: [
          { name: 'account_key', type: 'string', req: true, desc: 'Tenant account key' },
          { name: 'site_key',    type: 'string', req: true, desc: 'Site key' },
        ],
        response: '{ success, fields: { "contact.phone_admin": "...", "contact.website": "...", ... } }',
        note: 'Only public (visibility=public) KB resources are included. Private resources are silently excluded.',
      },
    ],
  },
  {
    id: 'sessions',
    label: 'Sessions',
    badge: 'bg-primary',
    description: 'Read-only session access. Auth via account_key + site_key + X-Sarah-Platform-Key header (server-to-server only).',
    endpoints: [
      {
        method: 'GET',
        path: '/sessions',
        summary: 'List sessions for a site.',
        auth: 'account_key + site_key (query params)',
        params: [
          { name: 'account_key', type: 'string', req: true,  desc: 'Tenant account key' },
          { name: 'site_key',    type: 'string', req: true,  desc: 'Site key' },
          { name: 'page',        type: 'int',    req: false, desc: 'Page number (default 1)' },
          { name: 'per_page',    type: 'int',    req: false, desc: 'Results per page (default 20)' },
        ],
        response: '{ success, sessions: [...], total, page, per_page }',
      },
      {
        method: 'GET',
        path: '/sessions/{uuid}',
        summary: 'Get a single session by UUID.',
        auth: 'account_key + site_key',
        params: [],
        response: '{ success, session: {...} }',
      },
      {
        method: 'GET',
        path: '/sessions/{uuid}/messages',
        summary: 'Get all messages for a session.',
        auth: 'account_key + site_key',
        params: [],
        response: '{ success, messages: [{role, content, created_at}] }',
      },
    ],
  },
  {
    id: 'tenants',
    label: 'Tenants',
    badge: 'bg-primary',
    description: 'Tenant management. Requires WordPress admin login (manage_options).',
    endpoints: [
      { method: 'GET',  path: '/tenants',                              summary: 'List all tenants.',                                auth: 'WP Admin', params: [], response: '{ success, data: [tenant...] }' },
      { method: 'POST', path: '/tenants',                              summary: 'Create a new tenant.',                            auth: 'WP Admin', params: [{ name: 'name, email, ...', type: 'string', req: true, desc: 'Tenant fields' }], response: '{ success, data: tenant }' },
      { method: 'GET',  path: '/tenants/{uuid}',                       summary: 'Get tenant with sites, users, subscription.',     auth: 'WP Admin', params: [], response: '{ success, data: { tenant, sites, users, subscription } }' },
      { method: 'POST', path: '/tenants/{uuid}/status',                summary: 'Update tenant status.',                          auth: 'WP Admin', params: [{ name: 'status', type: 'string', req: true, desc: 'active | inactive' }], response: '{ success }' },
      { method: 'POST', path: '/tenants/{uuid}/setup-complete',        summary: 'Mark tenant onboarding as complete.',            auth: 'WP Admin', params: [], response: '{ success }' },
      { method: 'GET',  path: '/tenants/{uuid}/available-agents',      summary: 'List agents allowed by the tenant\'s plan.',     auth: 'WP Admin', params: [], response: '{ success, data: [agent...] }' },
      { method: 'GET',  path: '/tenants/{uuid}/account-keys',          summary: 'List account keys for a tenant.',                auth: 'WP Admin', params: [], response: '{ success, data: [key...] }' },
      { method: 'POST', path: '/tenants/{uuid}/account-keys',          summary: 'Create an account key for a tenant.',            auth: 'WP Admin', params: [], response: '{ success, data: key }' },
      { method: 'GET',  path: '/tenants/{uuid}/users',                 summary: 'List WordPress users linked to a tenant.',       auth: 'WP Admin', params: [], response: '{ success, data: [user...] }' },
      { method: 'POST', path: '/tenants/{uuid}/users',                 summary: 'Link a WordPress user to a tenant.',             auth: 'WP Admin', params: [{ name: 'wp_user_id', type: 'int', req: true, desc: 'WP user ID' }], response: '{ success }' },
      { method: 'DELETE', path: '/tenants/{uuid}/users/{wp_user_id}',  summary: 'Unlink a user from a tenant.',                  auth: 'WP Admin', params: [], response: '{ success }' },
      { method: 'GET',  path: '/tenants/{uuid}/sites',                 summary: 'List sites for a tenant.',                      auth: 'WP Admin', params: [], response: '{ success, data: [site...] }' },
    ],
  },
  {
    id: 'sites',
    label: 'Sites',
    badge: 'bg-primary',
    description: 'Site management. Requires WordPress admin login.',
    endpoints: [
      { method: 'POST',   path: '/sites',                       summary: 'Create a site for a tenant.',             auth: 'WP Admin', params: [{ name: 'tenant_uuid, name, url', type: 'string', req: true, desc: 'Site fields' }], response: '{ success, data: site }' },
      { method: 'GET',    path: '/sites/{uuid}',                summary: 'Get a site by UUID.',                     auth: 'WP Admin', params: [], response: '{ success, data: site }' },
      { method: 'POST',   path: '/sites/{uuid}/status',         summary: 'Update site status.',                     auth: 'WP Admin', params: [{ name: 'status', type: 'string', req: true, desc: 'active | inactive' }], response: '{ success }' },
      { method: 'POST',   path: '/sites/{uuid}/agent',          summary: 'Assign an agent to a site.',              auth: 'WP Admin', params: [{ name: 'agent_id', type: 'int', req: true, desc: 'Agent ID' }], response: '{ success }' },
      { method: 'DELETE', path: '/sites/{uuid}/agent',          summary: 'Unassign agent from a site.',             auth: 'WP Admin', params: [], response: '{ success }' },
      { method: 'GET',    path: '/sites/{uuid}/agent-identity', summary: 'Get agent identity config for a site.',   auth: 'WP Admin', params: [], response: '{ success, data: identity }' },
      { method: 'POST',   path: '/sites/{uuid}/agent-identity', summary: 'Update agent identity for a site.',       auth: 'WP Admin', params: [{ name: 'agent_display_name, intro_message', type: 'string', req: false, desc: 'Identity fields' }], response: '{ success }' },
      { method: 'GET',    path: '/sites/{uuid}/site-keys',      summary: 'List site keys.',                         auth: 'WP Admin', params: [], response: '{ success, data: [key...] }' },
      { method: 'POST',   path: '/sites/{uuid}/site-keys',      summary: 'Create a site key.',                      auth: 'WP Admin', params: [], response: '{ success, data: key }' },
      { method: 'DELETE', path: '/site-keys/{uuid}',            summary: 'Delete a site key.',                      auth: 'WP Admin', params: [], response: '{ success }' },
    ],
  },
  {
    id: 'knowledge',
    label: 'Knowledge Base',
    badge: 'bg-primary',
    description: 'Knowledge resource management and processing pipeline. Requires WordPress admin login.',
    endpoints: [
      { method: 'GET',    path: '/knowledge-resource-types',                    summary: 'List enabled resource types.',                        auth: 'WP Admin', params: [], response: '{ success, types: [{type_key, label}] }' },
      { method: 'GET',    path: '/knowledge-resources',                         summary: 'List resources for a site.',                         auth: 'WP Admin', params: [{ name: 'site_uuid', type: 'string', req: true, desc: 'Site UUID' }], response: '{ success, data: [resource...] }' },
      { method: 'POST',   path: '/knowledge-resources',                         summary: 'Create a text or link resource.',                    auth: 'WP Admin', params: [{ name: 'site_uuid, title, resource_type, source_content', type: 'string', req: true, desc: '' }], response: '{ success, data: resource }' },
      { method: 'POST',   path: '/knowledge-resources/upload',                  summary: 'Upload a file resource (pdf/docx).',                 auth: 'WP Admin', params: [{ name: 'file, site_uuid, title', type: 'multipart', req: true, desc: 'Multipart form upload' }], response: '{ success, data: resource }', note: 'Content-Type: multipart/form-data' },
      { method: 'DELETE', path: '/knowledge-resources/{uuid}',                  summary: 'Soft-delete a resource.',                            auth: 'WP Admin', params: [], response: '{ success }' },
      { method: 'POST',   path: '/knowledge-resources/{uuid}/status',           summary: 'Update resource status.',                            auth: 'WP Admin', params: [{ name: 'status', type: 'string', req: true, desc: 'active | inactive' }], response: '{ success }' },
      { method: 'POST',   path: '/knowledge-resources/{uuid}/visibility',       summary: 'Toggle resource visibility.',                        auth: 'WP Admin', params: [{ name: 'visibility', type: 'string', req: true, desc: 'public | private' }], response: '{ success, uuid, visibility }' },
      { method: 'POST',   path: '/knowledge-resources/{uuid}/process',          summary: 'Run processing pipeline (extract → chunk → embed).', auth: 'WP Admin', params: [], response: '{ success, chunks, message }' },
      { method: 'GET',    path: '/knowledge-resources/{uuid}/chunks',           summary: 'List chunks produced by processing.',                auth: 'WP Admin', params: [], response: '{ success, chunk_count, chunks: [...] }' },
    ],
  },
  {
    id: 'agents',
    label: 'Agents',
    badge: 'bg-primary',
    description: 'Agent configuration. Requires WordPress admin login.',
    endpoints: [
      { method: 'GET',  path: '/agents',               summary: 'List all active agents.',                auth: 'WP Admin', params: [], response: '{ success, data: [agent...] }' },
      { method: 'POST', path: '/agents/{id}/behavior', summary: 'Update agent role, tone, system prompt.', auth: 'WP Admin', params: [{ name: 'role, tone, system_prompt', type: 'string', req: false, desc: 'Agent behaviour fields' }], response: '{ success, data: agent }' },
    ],
  },
  {
    id: 'plans',
    label: 'Plans',
    badge: 'bg-primary',
    description: 'Plan management. Requires WordPress admin login.',
    endpoints: [
      { method: 'GET',  path: '/plans',                 summary: 'List all plans with their allowed agents.', auth: 'WP Admin', params: [], response: '{ success, data: [plan...] }' },
      { method: 'GET',  path: '/plans/{id}/agents',     summary: 'Get agents allowed for a plan.',           auth: 'WP Admin', params: [], response: '{ success, data: [agent...] }' },
      { method: 'POST', path: '/plans/{id}/agents',     summary: 'Replace agent list for a plan.',           auth: 'WP Admin', params: [{ name: 'agent_ids', type: 'int[]', req: true, desc: 'Array of agent IDs' }], response: '{ success }' },
    ],
  },
  {
    id: 'platform',
    label: 'Platform Settings',
    badge: 'bg-primary',
    description: 'Global platform settings. Requires WordPress admin login.',
    endpoints: [
      { method: 'GET',  path: '/platform-settings', summary: 'Get all platform settings.',    auth: 'WP Admin', params: [], response: '{ success, data: { openai_api_key, platform_name, ... } }' },
      { method: 'POST', path: '/platform-settings', summary: 'Update platform settings.',     auth: 'WP Admin', params: [{ name: '...', type: 'object', req: false, desc: 'Key-value setting pairs' }], response: '{ success }' },
    ],
  },
];

// ─── Components ───────────────────────────────────────────────────────────────

const METHOD_COLORS = {
  GET:    'text-bg-success',
  POST:   'text-bg-primary',
  DELETE: 'text-bg-danger',
  PUT:    'text-bg-warning',
};

function CopyButton({ text }) {
  const [copied, setCopied] = useState(false);
  function copy() {
    navigator.clipboard?.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  }
  return (
    <button className="btn btn-sm btn-outline-secondary py-0 px-1 ms-2" style={{ fontSize: '0.65rem' }} onClick={copy}>
      {copied ? '✓' : 'copy'}
    </button>
  );
}

function EndpointRow({ ep }) {
  const [open, setOpen] = useState(false);
  const fullPath = BASE + ep.path;

  return (
    <div className="border rounded mb-2">
      <div
        className="d-flex align-items-center gap-2 px-3 py-2"
        style={{ cursor: 'pointer', background: open ? '#f8f9fa' : 'white' }}
        onClick={() => setOpen(o => !o)}
      >
        <span className={`badge ${METHOD_COLORS[ep.method] ?? 'text-bg-secondary'}`} style={{ minWidth: '52px', textAlign: 'center' }}>
          {ep.method}
        </span>
        <code className="text-dark small flex-grow-1">{ep.path}</code>
        <span className="text-muted small d-none d-md-inline">{ep.summary}</span>
        <span className="text-muted" style={{ fontSize: '0.7rem' }}>{open ? '▲' : '▼'}</span>
      </div>

      {open && (
        <div className="border-top px-3 py-3 bg-white">
          <p className="mb-2 text-muted small">{ep.summary}</p>

          {ep.note && (
            <div className="alert alert-warning py-1 px-2 small mb-2">{ep.note}</div>
          )}

          <div className="mb-2">
            <span className="text-muted small fw-semibold">Auth: </span>
            <span className="badge bg-light text-dark border small">{ep.auth}</span>
          </div>

          <div className="mb-2 d-flex align-items-center">
            <span className="text-muted small fw-semibold me-1">Endpoint:</span>
            <code className="small">{fullPath}</code>
            <CopyButton text={fullPath} />
          </div>

          {ep.params.length > 0 && (
            <div className="mb-2">
              <div className="text-muted small fw-semibold mb-1">Parameters:</div>
              <table className="table table-sm table-bordered mb-0" style={{ fontSize: '0.78rem' }}>
                <thead className="table-light">
                  <tr><th>Name</th><th>Type</th><th>Required</th><th>Description</th></tr>
                </thead>
                <tbody>
                  {ep.params.map((p, i) => (
                    <tr key={i}>
                      <td><code>{p.name}</code></td>
                      <td className="text-muted">{p.type}</td>
                      <td>{p.req ? <span className="text-danger fw-semibold">✓</span> : <span className="text-muted">—</span>}</td>
                      <td className="text-muted">{p.desc}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <div>
            <span className="text-muted small fw-semibold">Response: </span>
            <code className="small text-success">{ep.response}</code>
          </div>
        </div>
      )}
    </div>
  );
}

function GroupPanel({ group }) {
  return (
    <div className="mb-4">
      <div className="d-flex align-items-center gap-2 mb-1">
        <h6 className="mb-0 fw-semibold">{group.label}</h6>
        <span className={`badge ${group.badge}`} style={{ fontSize: '0.65rem' }}>
          {group.badge === 'bg-success' ? 'public' : 'admin'}
        </span>
        <span className="text-muted small">({group.endpoints.length} endpoints)</span>
      </div>
      <p className="text-muted small mb-2">{group.description}</p>
      {group.endpoints.map((ep, i) => <EndpointRow key={i} ep={ep} />)}
    </div>
  );
}

// ─── Page ────────────────────────────────────────────────────────────────────

export default function ApiGuide({ filter = 'all' }) {
  const publicGroups = GROUPS.filter(g => g.badge === 'bg-success');
  const adminGroups  = GROUPS.filter(g => g.badge !== 'bg-success');

  const visibleGroups = filter === 'public' ? publicGroups
                      : filter === 'admin'  ? adminGroups
                      : GROUPS;

  const visibleCount = visibleGroups.reduce((n, g) => n + g.endpoints.length, 0);

  const filterLabel = filter === 'public' ? 'Public endpoints only.'
                    : filter === 'admin'  ? 'Admin endpoints only (require WP login).'
                    : 'All REST endpoints exposed by sarah-ai-server.';

  return (
    <>
      <div className="mb-3">
        <p className="text-muted small mb-0">
          {filterLabel} Base URL: <code className="small">{'{site_url}'}{BASE}</code> &nbsp;·&nbsp; {visibleCount} endpoints
        </p>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          {visibleGroups.map(g => <GroupPanel key={g.id} group={g} />)}
        </div>
      </div>
    </>
  );
}
