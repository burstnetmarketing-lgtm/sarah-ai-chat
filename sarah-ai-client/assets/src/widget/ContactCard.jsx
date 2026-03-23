import React from 'react';
import { formatField } from './contactFormatter.js';
import { isCanonicalKey } from './knowledgeFieldSchema.js';

/**
 * ContactCard
 *
 * Renders a set of structured business contact fields in a clean card format.
 * Each field is formatted via contactFormatter and rendered as a labelled row
 * with an appropriate icon and a clickable link where applicable.
 *
 * Props:
 *   fields — array of { key, label, value } objects from a <sarah_card> block
 *
 * Policy validation:
 *   Only fields with canonical keys (defined in knowledgeFieldSchema.js) are rendered.
 *   Fields with unknown keys, empty values, or non-string values are silently dropped.
 *   This is a defence-in-depth measure — the server already enforces visibility,
 *   but the widget must not blindly render arbitrary key/value pairs from AI output.
 */
export default function ContactCard({ fields }) {
  if (!fields || fields.length === 0) return null;

  // Policy validation: only canonical keys with non-empty string values
  const validated = fields.filter(
    f => f && typeof f.key === 'string' && isCanonicalKey(f.key)
      && typeof f.value === 'string' && f.value.trim() !== ''
  );

  if (validated.length === 0) return null;

  const formatted = validated.map(formatField).filter(f => f.displayValue);

  if (formatted.length === 0) return null;

  return (
    <div className="sac-contact-card">
      {formatted.map((f, i) => (
        <div key={i} className="sac-contact-row">
          <span className="sac-contact-icon" aria-hidden="true">{f.icon}</span>
          {f.label && (
            <span className="sac-contact-label">{f.label}:</span>
          )}
          {f.href ? (
            <a
              className="sac-contact-value sac-contact-link"
              href={f.href}
              target={f.type === 'website' || f.type === 'address' ? '_blank' : undefined}
              rel="noopener noreferrer"
            >
              {f.displayValue}
            </a>
          ) : (
            <span className="sac-contact-value">{f.displayValue}</span>
          )}
        </div>
      ))}
    </div>
  );
}
