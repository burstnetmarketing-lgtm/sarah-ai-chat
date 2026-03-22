import { apiFetch } from './client.js';

// Tenants
export const listTenants        = ()                   => apiFetch('tenants');
export const createTenant       = (data)               => apiFetch('tenants', 'POST', data);
export const getTenant          = (uuid)               => apiFetch(`tenants/${uuid}`);
export const updateTenantStatus = (uuid, status)       => apiFetch(`tenants/${uuid}/status`, 'POST', { status });

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
export const listAgents           = ()                => apiFetch('agents');
export const assignAgent          = (siteUuid, agentId) => apiFetch(`sites/${siteUuid}/agent`, 'POST', { agent_id: agentId });
export const listAvailableAgents  = (tenantUuid)      => apiFetch(`tenants/${tenantUuid}/available-agents`);

// Plans
export const listPlans        = ()                    => apiFetch('plans');
export const getPlanAgents    = (planId)              => apiFetch(`plans/${planId}/agents`);
export const syncPlanAgents   = (planId, agentIds)    => apiFetch(`plans/${planId}/agents`, 'POST', { agent_ids: agentIds });

// Knowledge Resources
export const listKnowledge         = (siteUuid)       => apiFetch(`knowledge-resources?site_uuid=${siteUuid}`);
export const createKnowledge       = (data)           => apiFetch('knowledge-resources', 'POST', data);
export const deleteKnowledge       = (uuid)           => apiFetch(`knowledge-resources/${uuid}`, 'DELETE');
export const updateKnowledgeStatus = (uuid, status)   => apiFetch(`knowledge-resources/${uuid}/status`, 'POST', { status });
