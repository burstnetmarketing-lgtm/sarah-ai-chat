import { apiFetch } from './client.js';

// Tenants
export const listTenants        = ()                   => apiFetch('tenants');
export const createTenant       = (data)               => apiFetch('tenants', 'POST', data);
export const quickCreateTenant  = (data)               => apiFetch('tenants/quick-create', 'POST', data);
export const getTenant          = (uuid)               => apiFetch(`tenants/${uuid}`);
export const updateTenantStatus      = (uuid, status)  => apiFetch(`tenants/${uuid}/status`, 'POST', { status });
export const markTenantSetupComplete = (uuid)          => apiFetch(`tenants/${uuid}/setup-complete`, 'POST');
export const deleteTenant            = (uuid)          => apiFetch(`tenants/${uuid}`, 'DELETE');

// Users
export const listTenantUsers  = (tenantUuid)              => apiFetch(`tenants/${tenantUuid}/users`);
export const addTenantUser    = (tenantUuid, data)         => apiFetch(`tenants/${tenantUuid}/users`, 'POST', data);
export const removeTenantUser = (tenantUuid, wpUserId)     => apiFetch(`tenants/${tenantUuid}/users/${wpUserId}`, 'DELETE');

// Sites
export const createSite       = (data)                => apiFetch('sites', 'POST', data);
export const getSite          = (uuid)                => apiFetch(`sites/${uuid}`);
export const updateSiteStatus = (uuid, status)        => apiFetch(`sites/${uuid}/status`, 'POST', { status });

// Account Keys
export const listAccountKeys  = (tenantUuid)          => apiFetch(`tenants/${tenantUuid}/account-keys`);
export const createAccountKey = (tenantUuid, data)    => apiFetch(`tenants/${tenantUuid}/account-keys`, 'POST', data);
export const deleteAccountKey = (uuid)                => apiFetch(`account-keys/${uuid}`, 'DELETE');

// Site Keys
export const listSiteKeys  = (siteUuid)               => apiFetch(`sites/${siteUuid}/site-keys`);
export const createSiteKey = (siteUuid, data)         => apiFetch(`sites/${siteUuid}/site-keys`, 'POST', data);
export const deleteSiteKey = (uuid)                   => apiFetch(`site-keys/${uuid}`, 'DELETE');

// Agents
export const listAgents           = ()                      => apiFetch('agents');
export const assignAgent          = (siteUuid, agentId)     => apiFetch(`sites/${siteUuid}/agent`, 'POST', { agent_id: agentId });
export const unassignAgent        = (siteUuid)              => apiFetch(`sites/${siteUuid}/agent`, 'DELETE');
export const updateAgentBehavior  = (agentId, data)         => apiFetch(`agents/${agentId}/behavior`, 'PUT', data);
export const getAgentIdentity     = (siteUuid)              => apiFetch(`sites/${siteUuid}/agent-identity`);
export const updateAgentIdentity  = (siteUuid, data)        => apiFetch(`sites/${siteUuid}/agent-identity`, 'POST', data);
export const getSiteAgentConfig   = (siteUuid)              => apiFetch(`sites/${siteUuid}/agent-config`);
export const updateSiteAgentConfig = (siteUuid, data)       => apiFetch(`sites/${siteUuid}/agent-config`, 'POST', data);
export const listAvailableAgents  = (tenantUuid)            => apiFetch(`tenants/${tenantUuid}/available-agents`);

// Plans
export const listPlans        = ()                    => apiFetch('plans');
export const getPlanAgents    = (planId)              => apiFetch(`plans/${planId}/agents`);
export const syncPlanAgents   = (planId, agentIds)    => apiFetch(`plans/${planId}/agents`, 'POST', { agent_ids: agentIds });

// Platform Settings
export const getPlatformSettings    = ()     => apiFetch('platform-settings');
export const updatePlatformSettings = (data) => apiFetch('platform-settings', 'POST', data);

// Usage
export const getUsage        = (params = {}) => apiFetch('usage?' + new URLSearchParams(params).toString());
export const getUsageSummary = (params = {}) => apiFetch('usage/summary?' + new URLSearchParams(params).toString());

// Knowledge Resources
export const getKnowledgeResourceTypes = ()           => apiFetch('knowledge-resource-types');
export const listKnowledge             = (siteUuid)   => apiFetch(`knowledge-resources?site_uuid=${siteUuid}`);
export const createKnowledge       = (data)           => apiFetch('knowledge-resources', 'POST', data);
export const deleteKnowledge       = (uuid)           => apiFetch(`knowledge-resources/${uuid}`, 'DELETE');
export const updateKnowledgeStatus = (uuid, status)   => apiFetch(`knowledge-resources/${uuid}/status`, 'POST', { status });
export const processKnowledge         = (uuid)            => apiFetch(`knowledge-resources/${uuid}/process`, 'POST');
export const updateKnowledgeVisibility = (uuid, visibility) => apiFetch(`knowledge-resources/${uuid}/visibility`, 'POST', { visibility });
export const getKnowledgeChunks    = (uuid)           => apiFetch(`knowledge-resources/${uuid}/chunks`);

export async function uploadKnowledgeFile(siteUuid, file, title = '') {
  const { apiUrl, nonce } = window.SarahAiServerConfig || {};
  const formData = new FormData();
  formData.append('file', file);
  formData.append('site_uuid', siteUuid);
  if (title) formData.append('title', title);
  const res = await fetch(`${apiUrl}/knowledge-resources/upload`, {
    method: 'POST',
    headers: { 'X-WP-Nonce': nonce },
    body: formData,
  });
  return res.json();
}
