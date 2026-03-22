import { apiFetch } from './client.js';

// Tenants
export const listTenants       = ()           => apiFetch('tenants');
export const createTenant      = (data)       => apiFetch('tenants', 'POST', data);
export const getTenant         = (id)         => apiFetch(`tenants/${id}`);
export const updateTenantStatus = (id, status) => apiFetch(`tenants/${id}/status`, 'POST', { status });

// Users
export const listTenantUsers  = (tenantId)           => apiFetch(`tenants/${tenantId}/users`);
export const addTenantUser    = (tenantId, data)      => apiFetch(`tenants/${tenantId}/users`, 'POST', data);
export const removeTenantUser = (tenantId, wpUserId)  => apiFetch(`tenants/${tenantId}/users/${wpUserId}`, 'DELETE');

// Sites
export const createSite      = (data) => apiFetch('sites', 'POST', data);
export const getSite         = (id)   => apiFetch(`sites/${id}`);
export const updateSiteStatus = (id, status) => apiFetch(`sites/${id}/status`, 'POST', { status });

// Account Keys
export const listAccountKeys  = (tenantId) => apiFetch(`tenants/${tenantId}/account-keys`);
export const createAccountKey = (tenantId, data) => apiFetch(`tenants/${tenantId}/account-keys`, 'POST', data);
export const deleteAccountKey = (id)       => apiFetch(`account-keys/${id}`, 'DELETE');

// Site Keys
export const listSiteKeys  = (siteId) => apiFetch(`sites/${siteId}/site-keys`);
export const createSiteKey = (siteId, data) => apiFetch(`sites/${siteId}/site-keys`, 'POST', data);
export const deleteSiteKey = (id)     => apiFetch(`site-keys/${id}`, 'DELETE');

// Agents
export const listAgents   = ()               => apiFetch('agents');
export const assignAgent  = (siteId, agentId) => apiFetch(`sites/${siteId}/agent`, 'POST', { agent_id: agentId });

// Knowledge Resources
export const listKnowledge  = (siteId) => apiFetch(`knowledge-resources?site_id=${siteId}`);
export const createKnowledge = (data)  => apiFetch('knowledge-resources', 'POST', data);
export const deleteKnowledge = (id)    => apiFetch(`knowledge-resources/${id}`, 'DELETE');
export const updateKnowledgeStatus = (id, status) => apiFetch(`knowledge-resources/${id}/status`, 'POST', { status });
