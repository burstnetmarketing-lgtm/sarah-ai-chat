/**
 * Business data provider — fetches structured KB fields from the server.
 *
 * Data source: GET /sarah-ai-server/v1/sites/{uuid}/knowledge-fields
 * Auth: account_key + site_key from window.SarahAiWidget.connection
 *
 * Only public fields are returned by the server (visibility enforcement
 * is done server-side by KnowledgePolicyFilter + KnowledgeFieldsController).
 *
 * Fields are canonical key → string value pairs as defined in knowledgeFieldSchema.js.
 * Non-canonical keys are stripped by filterCanonicalFields() before use.
 */

import { filterCanonicalFields } from './knowledgeFieldSchema.js';

let _cache = null;        // resolved fields map, or null if not yet fetched
let _fetchPromise = null; // in-flight fetch — deduplicate concurrent calls

/**
 * Fetches public structured fields for the current site from the KB API.
 * Results are cached for the widget session.
 *
 * @returns {Promise<Record<string, string>>}  Canonical key → value map (may be empty)
 */
export async function fetchBusinessFields() {
  if (_cache !== null) return _cache;
  if (_fetchPromise) return _fetchPromise;

  const conn = window.SarahAiWidget?.connection || {};
  const { server_url, account_key, site_key, site_uuid } = conn;

  if (!server_url || !account_key || !site_key || !site_uuid) {
    _cache = {};
    return _cache;
  }

  _fetchPromise = fetch(
    `${server_url}/sites/${site_uuid}/knowledge-fields?account_key=${encodeURIComponent(account_key)}&site_key=${encodeURIComponent(site_key)}`
  )
    .then(res => res.json())
    .then(data => {
      _cache = data.success ? filterCanonicalFields(data.fields ?? {}) : {};
      return _cache;
    })
    .catch(() => {
      _cache = {};
      return _cache;
    })
    .finally(() => {
      _fetchPromise = null;
    });

  return _fetchPromise;
}

/**
 * Returns a single field value by canonical key.
 *
 * @param {string} key  Canonical key (e.g. 'contact.phone_admin')
 * @returns {Promise<string|null>}
 */
export async function getBusinessField(key) {
  const fields = await fetchBusinessFields();
  return fields[key] ?? null;
}

/**
 * Returns all available business fields.
 *
 * @returns {Promise<Record<string, string>>}
 */
export async function getAllBusinessFields() {
  return fetchBusinessFields();
}

/**
 * Clears the field cache — useful for forced refresh.
 */
export function clearBusinessFieldsCache() {
  _cache = null;
  _fetchPromise = null;
}
