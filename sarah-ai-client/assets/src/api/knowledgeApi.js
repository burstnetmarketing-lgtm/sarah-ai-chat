/**
 * Knowledge Base API — connects the admin panel to the sarah-ai-server
 * client KB endpoints (/client/knowledge-*).
 *
 * Auth: account_key + site_key (body/query) + X-Sarah-Platform-Key (header).
 * All values are read from window.SarahAiWidget.connection.
 */

function getConn() {
  return window.SarahAiWidget?.connection || {};
}

function requireConn() {
  const { server_url, account_key, site_key, platform_key } = getConn();
  if (!server_url || !account_key || !site_key || !platform_key) {
    throw new Error('Sarah AI: missing server connection config (server_url, account_key, site_key, platform_key).');
  }
  return { server_url, account_key, site_key, platform_key };
}

function authHeaders(platform_key) {
  return {
    'Content-Type':        'application/json',
    'X-Sarah-Platform-Key': platform_key,
  };
}

async function apiGet(path, params = {}) {
  const { server_url, account_key, site_key, platform_key } = requireConn();
  const qs = new URLSearchParams({ account_key, site_key, ...params });
  const res = await fetch(`${server_url}${path}?${qs}`, {
    headers: authHeaders(platform_key),
  });
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || `HTTP ${res.status}`);
  return data;
}

async function apiPost(path, body = {}) {
  const { server_url, account_key, site_key, platform_key } = requireConn();
  const res = await fetch(`${server_url}${path}`, {
    method:  'POST',
    headers: authHeaders(platform_key),
    body:    JSON.stringify({ account_key, site_key, ...body }),
  });
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || `HTTP ${res.status}`);
  return data;
}

async function apiDelete(path, params = {}) {
  const { server_url, account_key, site_key, platform_key } = requireConn();
  const qs = new URLSearchParams({ account_key, site_key, ...params });
  const res = await fetch(`${server_url}${path}?${qs}`, {
    method:  'DELETE',
    headers: authHeaders(platform_key),
  });
  const data = await res.json();
  if (!res.ok || !data.success) throw new Error(data.message || `HTTP ${res.status}`);
  return data;
}

// ─── Public API ──────────────────────────────────────────────────────────────

/** Returns enabled resource types: [{ type_key, label }] */
export async function getKnowledgeResourceTypes() {
  return apiGet('/client/knowledge-resource-types');
}

/** Returns all KB resources for this site: { data: [...] } */
export async function listKnowledgeResources() {
  return apiGet('/client/knowledge-resources');
}

/**
 * Creates a new KB resource.
 * @param {{ resource_type, title, source_content, content_group?, meta? }} fields
 */
export async function createKnowledgeResource(fields) {
  return apiPost('/client/knowledge-resources', fields);
}

/**
 * Soft-deletes a resource by UUID.
 * @param {string} uuid
 */
export async function deleteKnowledgeResource(uuid) {
  return apiDelete(`/client/knowledge-resources/${uuid}`);
}

/**
 * Updates a resource's status.
 * @param {string} uuid
 * @param {'active'|'inactive'|'pending'|'archived'} status
 */
export async function updateKnowledgeResourceStatus(uuid, status) {
  return apiPost(`/client/knowledge-resources/${uuid}/status`, { status });
}

/**
 * Triggers the processing pipeline (extract → chunk → embed) for a resource.
 * @param {string} uuid
 */
export async function processKnowledgeResource(uuid) {
  return apiPost(`/client/knowledge-resources/${uuid}/process`);
}
