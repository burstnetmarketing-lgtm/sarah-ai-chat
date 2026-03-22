/**
 * Business Data Provider
 *
 * Supplies structured business field values by canonical key.
 * This is the single source of truth for business contact/info data
 * accessible to the widget without going through the AI.
 *
 * Canonical key format:
 *   contact.phone_admin     — main admin phone number
 *   contact.phone_marketing — marketing phone number
 *   contact.website         — website URL
 *   contact.email_support   — support email address
 *   business.address        — physical address
 *   business.hours          — trading / opening hours
 *
 * Current state: mock/static placeholder.
 *
 * TODO: replace mock provider with KB-backed provider
 * TODO: support tenant-specific business data source
 * TODO: add policy/access-control filtering before formatter
 * TODO: move business fields to canonical KB schema
 * TODO: validate and sanitize structured values before render
 */

// TODO: replace mock provider with KB-backed provider
// These values are intentionally empty — replace with KB API integration.
// The provider exists as an architectural placeholder so components depend on
// this interface, not on hardcoded values scattered across the codebase.
const _fields = {};

/**
 * Get a single business field by canonical key.
 * Returns null if the field is not available.
 *
 * @param {string} key  e.g. 'contact.phone_admin'
 * @returns {string|null}
 */
export function getBusinessField(key) {
  // TODO: support tenant-specific business data source
  return _fields[key] ?? null;
}

/**
 * Get all available business fields as a key→value map.
 *
 * @returns {object}
 */
export function getAllBusinessFields() {
  // TODO: replace mock provider with KB-backed provider
  return { ..._fields };
}
