/**
 * Contact Formatter
 *
 * Converts raw structured field values into display-ready descriptors.
 * Works with key-value pairs from AI structured responses or the business provider.
 *
 * Field type is inferred from the canonical key prefix:
 *   contact.phone_*   → formatted phone + tel: link
 *   contact.mobile_*  → same as phone
 *   contact.email_*   → display email + mailto: link
 *   contact.website   → display URL + https: link
 *   business.address  → display address + Google Maps link
 *   business.hours    → plain text display
 *
 * TODO: validate and sanitize structured values before render
 * TODO: move business fields to canonical KB schema
 */

const ICONS = {
  phone:   '📞',
  email:   '✉️',
  website: '🌐',
  address: '📍',
  hours:   '🕐',
  default: '•',
};

/**
 * Normalise a raw phone string to a continuous digit string (no spaces).
 * Spaces are intentionally avoided to prevent digit reversal in RTL layouts.
 *
 * @param {string} raw
 * @returns {string}
 */
export function formatPhone(raw) {
  return String(raw).replace(/\D/g, '');
}

/**
 * Normalise a website value to a full HTTPS URL.
 * "www.example.com"       → "https://www.example.com"
 * "http://example.com"    → "http://example.com" (preserved)
 * "https://example.com"   → unchanged
 *
 * @param {string} raw
 * @returns {string}
 */
export function formatWebsite(raw) {
  const trimmed = String(raw).trim();
  if (/^https?:\/\//i.test(trimmed)) return trimmed;
  return 'https://' + trimmed;
}

/**
 * Format a field descriptor { key, label, value } into a renderable object.
 * Returns:
 *   { label, value, displayValue, href, icon, type }
 *
 * - displayValue: user-visible text (formatted phone, clean domain, etc.)
 * - href:         actionable link (tel:, mailto:, https:, maps) or null
 * - icon:         emoji icon appropriate to the field type
 * - type:         'phone' | 'email' | 'website' | 'address' | 'hours' | 'text'
 *
 * TODO: validate and sanitize structured values before render
 *
 * @param {{ key: string, label: string, value: string }} field
 * @returns {{ label: string, value: string, displayValue: string, href: string|null, icon: string, type: string }}
 */
export function formatField(field) {
  const key   = String(field.key   ?? '').toLowerCase();
  const label = String(field.label ?? '');
  const value = String(field.value ?? '').trim();

  if (!value) {
    return { label, value, displayValue: value, href: null, icon: ICONS.default, type: 'text' };
  }

  // Phone
  if (key.startsWith('contact.phone') || key.startsWith('contact.mobile')) {
    return {
      label,
      value,
      displayValue: formatPhone(value),
      href:         'tel:' + value.replace(/\D/g, ''),
      icon:         ICONS.phone,
      type:         'phone',
    };
  }

  // Email
  if (key.startsWith('contact.email') || key.includes('.email')) {
    return {
      label,
      value,
      displayValue: value,
      href:         'mailto:' + value,
      icon:         ICONS.email,
      type:         'email',
    };
  }

  // Website
  if (key === 'contact.website' || key.includes('.website') || key.includes('.url')) {
    const href    = formatWebsite(value);
    const display = value.replace(/^https?:\/\//i, '').replace(/\/$/, '');
    return {
      label,
      value,
      displayValue: display,
      href,
      icon:         ICONS.website,
      type:         'website',
    };
  }

  // Address
  if (key.startsWith('business.address') || key.includes('.address')) {
    return {
      label,
      value,
      displayValue: value,
      href:         'https://maps.google.com/?q=' + encodeURIComponent(value),
      icon:         ICONS.address,
      type:         'address',
    };
  }

  // Hours
  if (key.startsWith('business.hours') || key.includes('.hours')) {
    return {
      label,
      value,
      displayValue: value,
      href:         null,
      icon:         ICONS.hours,
      type:         'hours',
    };
  }

  // Unknown — plain text
  return { label, value, displayValue: value, href: null, icon: ICONS.default, type: 'text' };
}
