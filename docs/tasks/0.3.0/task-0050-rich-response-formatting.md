# Task 0050 — Rich Business Response Formatting

## Goal
Convert raw AI contact/business text into structured, formatted cards with clickable phone, website, email, and map links.

## Architecture

```
AI response (with <sarah_card> tag)
    ↓ parseAiResponse()        — strips tag, extracts JSON
    ↓ ContactCard              — renders each field via formatField()
    ↓ contactFormatter.js      — formats phone / website / email / address
    ↓ widget CSS               — .sac-contact-card layout
```

Future flow:
```
KB / API → Policy Filter → Data Provider → Formatter → UI
```

## New Files

### `assets/src/widget/businessProvider.js`
- Provider placeholder for business field data
- `getBusinessField(key)` — returns null for now (all TODOs for KB integration)
- `getAllBusinessFields()` — returns empty map
- Contains explicit TODO markers for KB integration, tenant-specific data, and access-control filtering

### `assets/src/widget/contactFormatter.js`
- `formatPhone(raw)` — AU 10-digit mobile (04XX XXX XXX) + landline (0X XXXX XXXX) + international (+61)
- `formatWebsite(raw)` — normalises to https:// URL, strips protocol for display
- `formatField(field)` — maps `{ key, label, value }` → `{ label, value, displayValue, href, icon, type }`
  - Infers type from canonical key prefix (`contact.phone_*`, `contact.email_*`, `contact.website`, `business.address`, `business.hours`)
  - Generates actionable `href` (`tel:`, `mailto:`, `https:`, `maps.google.com`)

### `assets/src/widget/ContactCard.jsx`
- Renders `fields[]` as a card below AI bubble text
- Each row: icon + label + value (clickable if href exists)
- Phone + email: same-tab links; website + address: `target="_blank"`
- Returns `null` if no displayable fields

## Modified Files

### `assets/css/widget.css`
- `.sac-contact-card` — bordered card, flex column
- `.sac-contact-row` — icon + label + value row
- `.sac-contact-icon`, `.sac-contact-label`, `.sac-contact-value`
- `.sac-contact-link` — navy colour + underline, hover effect
- Also added `.sac-bubble-error`, `.sac-retry-btn`, `.sac-header-actions`, `.sac-reset-btn` (were missing)

### `assets/src/widget/ChatWindow.jsx`
- Added `parseAiResponse(rawText)` function:
  - Matches `<sarah_card>…</sarah_card>` (case-insensitive)
  - Strips tag from display text
  - Parses JSON and validates `fields` array
  - Returns `{ text, cardData }` — `cardData` is null if no tag or malformed JSON
- Applied to: new AI responses (`data.message`) and history restore (assistant messages)
- Message objects now carry optional `cardData` field

### `assets/src/widget/MessageArea.jsx`
- Imports `ContactCard`
- Renders `<ContactCard fields={msg.cardData.fields} />` below bubble text when `cardData` is present

### `includes/Runtime/OpenAiAgentExecutor.php`
- Added `buildStructuredOutputInstruction()` — appended to BOTH custom and composed system prompts
- Instructs the AI to append `<sarah_card>{"type":"contact","fields":[...]}` at the very end of any response that mentions contact info
- Documents canonical key names for the AI to use

## Canonical Key Names

| Key | Type |
|-----|------|
| `contact.phone_admin` | phone |
| `contact.phone_marketing` | phone |
| `contact.website` | website |
| `contact.email_support` | email |
| `business.address` | address |
| `business.hours` | hours |

## TODO Markers (Future KB Integration)

All placed in source code:
- `businessProvider.js` — 5 TODOs for KB-backed data, tenant-specific source, access-control
- `contactFormatter.js` — 2 TODOs for validation and canonical KB schema
- `ContactCard.jsx` — 2 TODOs for access-control filtering and KB-driven fields
- `ChatWindow.jsx` — 2 TODOs near parseAiResponse

## Fallback Behavior
- `<sarah_card>` tag absent → no card rendered, message displays as plain text (unchanged)
- Malformed JSON inside tag → tag discarded, full raw text shown as-is
- Field with empty value → field skipped in ContactCard
- Unknown canonical key prefix → rendered as plain text row with bullet icon

## Commit
0035
