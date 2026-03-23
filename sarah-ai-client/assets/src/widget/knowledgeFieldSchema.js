/**
 * Canonical KB field key schema — JS mirror of KnowledgeFieldSchema.php.
 *
 * IMPORTANT: Keep in sync with:
 *   - sarah-ai-server/includes/Processing/KnowledgeFieldSchema.php
 *   - OpenAiAgentExecutor::buildStructuredOutputInstruction() canonical key list
 *
 * Any new key added here must also be added to the PHP class and the AI prompt.
 */

export const FIELD_PHONE_ADMIN     = 'contact.phone_admin';
export const FIELD_PHONE_MARKETING = 'contact.phone_marketing';
export const FIELD_PHONE_SALES     = 'contact.phone_sales';
export const FIELD_EMAIL_SUPPORT   = 'contact.email_support';
export const FIELD_EMAIL_SALES     = 'contact.email_sales';
export const FIELD_WEBSITE         = 'contact.website';
export const FIELD_ADDRESS         = 'business.address';
export const FIELD_HOURS           = 'business.hours';
export const FIELD_NAME            = 'business.name';
export const FIELD_DESCRIPTION     = 'business.description';

export const ALL_KEYS = new Set([
  FIELD_PHONE_ADMIN,
  FIELD_PHONE_MARKETING,
  FIELD_PHONE_SALES,
  FIELD_EMAIL_SUPPORT,
  FIELD_EMAIL_SALES,
  FIELD_WEBSITE,
  FIELD_ADDRESS,
  FIELD_HOURS,
  FIELD_NAME,
  FIELD_DESCRIPTION,
]);

/**
 * Returns true if the given key is a known canonical field.
 * Use this to validate fields before rendering to avoid UI injection.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function isCanonicalKey(key) {
  return ALL_KEYS.has(key);
}

/**
 * Filters a fields object, keeping only entries with canonical keys and non-empty string values.
 *
 * @param {Record<string, string>} fields
 * @returns {Record<string, string>}
 */
export function filterCanonicalFields(fields) {
  if (!fields || typeof fields !== 'object') return {};
  const out = {};
  for (const [key, value] of Object.entries(fields)) {
    if (isCanonicalKey(key) && typeof value === 'string' && value.trim() !== '') {
      out[key] = value.trim();
    }
  }
  return out;
}
