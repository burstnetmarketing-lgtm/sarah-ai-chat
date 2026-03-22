# UI Standards — Techmin Template Reference

This document defines the exact HTML/JSX patterns to follow for all admin UI pages.
All components are built on **Bootstrap 5** as used by the **Techmin React template**.
When building a new page, start here — do not invent custom patterns.

Source of truth: `docs/temp/Techmin-React_v1.0/Techmin/JS/src/`

---

## Table of Contents

1. [Page Layout](#page-layout)
2. [Cards](#cards)
3. [Tables](#tables)
4. [Badges](#badges)
5. [Buttons](#buttons)
6. [Forms](#forms)
7. [Modals](#modals)
8. [Icons](#icons)
9. [Custom Overrides](#custom-overrides)

---

## Page Layout

Every page is wrapped in a `row` > `col-12` grid:

```jsx
<div className="row">
  <div className="col-12">
    {/* card or content here */}
  </div>
</div>
```

For two-column layouts use `col-xl-6` or `col-lg-6`.

---

## Cards

### Standard Card with Header + Table

The most common pattern. Use when displaying a list/table with filter controls.

```jsx
<div className="card">

  {/* Header: title left, actions right */}
  <div className="d-flex justify-content-between flex-wrap align-items-center card-header">
    <div>
      <h4 className="card-title">Page Title</h4>
      <p className="text-muted fw-semibold mb-0">Short description.</p>
    </div>
    <div className="d-flex align-items-center gap-2 flex-wrap">
      {/* buttons, filters, search */}
    </div>
  </div>

  {/* Body: p-0 when containing a table */}
  <div className="p-0 card-body">
    {/* table-responsive wrapper goes here */}
  </div>

  {/* Optional footer */}
  <div className="card-footer text-muted small">
    Footer text
  </div>

</div>
```

### Simple Card with Body

```jsx
<div className="card">
  <div className="card-body">
    <h4 className="card-title">Card Title</h4>
    <p className="card-text">Content here.</p>
  </div>
</div>
```

### Key rules
- `card-header` always has title (`h4.card-title`) + optional subtitle (`p.text-muted.fw-semibold.mb-0`)
- Action buttons go on the **right** side of the header (`d-flex gap-2`)
- `card-body` gets `p-0` **only** when it contains a table (so table edges align with card edges)
- Use `card-footer` for counts or secondary info

---

## Tables

### Standard Table Pattern

```jsx
<div className="p-0 card-body">
  <div className="table-responsive">
    <table className="table align-middle mb-0">
      <thead>
        <tr className="table-light text-capitalize">
          <th>Column One</th>
          <th>Column Two</th>
          <th>Status</th>
          <th></th> {/* action column — no heading */}
        </tr>
      </thead>
      <tbody>
        {loading ? (
          <tr><td colSpan={4} className="text-muted py-4 text-center">Loading…</td></tr>
        ) : rows.length === 0 ? (
          <tr><td colSpan={4} className="text-muted py-4 text-center">No records found.</td></tr>
        ) : rows.map(row => (
          <tr key={row.id}>
            <td>
              {/* Primary identifier — use btn-link for clickable navigation */}
              <button className="btn btn-link p-0 text-start fw-semibold"
                      onClick={() => onNavigate('detail', row.uuid)}>
                {row.name}
              </button>
            </td>
            <td>
              <h5 className="mb-0">{row.secondary_field}</h5>
            </td>
            <td>
              <span className="badge bg-success-subtle text-success">{row.status}</span>
            </td>
            <td>
              {/* inline action, e.g. select or button */}
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  </div>
</div>
```

### Table rules
- Always `table align-middle mb-0`
- `thead > tr` always has `table-light text-capitalize`
- Column headers are lowercase (CSS text-capitalize handles display)
- Empty action column (`<th></th>`) at the end for per-row controls
- Loading and empty states use `colSpan` across all columns, `text-center py-4 text-muted`
- Primary field in first column: name/title as `btn btn-link` if it navigates somewhere
- Secondary fields use `<h5 className="mb-0">` or `<span className="fw-semibold">`
- Meta/dates use `<span className="text-muted">` or just plain `td`

### Table Filter Bar (in card-header)

```jsx
<div className="d-flex align-items-center gap-2 flex-wrap">
  {/* Tab-style status filter */}
  <div className="btn-group btn-group-sm">
    {OPTIONS.map(opt => (
      <button key={opt}
        type="button"
        className={`btn ${active === opt ? 'btn-secondary' : 'btn-outline-secondary'}`}
        onClick={() => setActive(opt)}>
        {LABELS[opt]}
      </button>
    ))}
  </div>

  {/* Search input */}
  <input
    type="text"
    className="form-control form-control-sm"
    style={{ width: '180px' }}
    placeholder="Search…"
    value={search}
    onChange={e => setSearch(e.target.value)}
  />

  {/* Refresh button */}
  <button type="button" className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>
    ↺
  </button>
</div>
```

---

## Badges

Use **subtle** (soft) badges for status. Never use solid backgrounds for status fields.

```jsx
{/* Status badges — always bg-{color}-subtle text-{color} */}
<span className="badge bg-primary-subtle text-primary">Trialing</span>
<span className="badge bg-success-subtle text-success">Active</span>
<span className="badge bg-danger-subtle text-danger">Cancelled</span>
<span className="badge bg-secondary-subtle text-secondary">Expired</span>
<span className="badge bg-warning-subtle text-warning">Pending</span>
<span className="badge bg-info-subtle text-info">Info</span>
```

Pill variant (add `rounded-pill`):

```jsx
<span className="badge bg-success-subtle text-success rounded-pill">Active</span>
```

Outline variant (use sparingly, only for non-status labels):

```jsx
<span className="badge badge-outline-primary rounded-pill">Primary</span>
```

### Badge color → meaning convention

| Color     | Meaning                          |
|-----------|----------------------------------|
| `success` | Active, complete, healthy        |
| `primary` | Trialing, in-progress            |
| `warning` | Pending, needs attention         |
| `danger`  | Cancelled, error, failed         |
| `secondary` | Expired, inactive, disabled    |
| `info`    | Informational, neutral           |

---

## Buttons

### Variants

```jsx
{/* Solid */}
<button className="btn btn-primary">Primary</button>
<button className="btn btn-secondary">Secondary</button>
<button className="btn btn-success">Success</button>
<button className="btn btn-danger">Danger</button>
<button className="btn btn-warning">Warning</button>
<button className="btn btn-info">Info</button>
<button className="btn btn-light">Light</button>
<button className="btn btn-dark">Dark</button>

{/* Outline */}
<button className="btn btn-outline-primary">Primary</button>
<button className="btn btn-outline-secondary">Secondary</button>
```

### Sizes

```jsx
<button className="btn btn-primary btn-lg">Large</button>
<button className="btn btn-primary">Normal</button>
<button className="btn btn-primary btn-sm">Small</button>
```

### With Icon (use `@iconify/react`)

```jsx
import { Icon } from '@iconify/react';

<button className="btn btn-outline-secondary me-2">
  <Icon icon="mdi:filter-outline" className="pe-1" height={18} width={18} />
  Filter
</button>

<button className="btn btn-outline-primary">See All</button>
```

### Navigable name (table cell)

```jsx
<button className="btn btn-link p-0 text-start fw-semibold"
        onClick={() => onNavigate('detail', item.uuid)}>
  {item.name}
</button>
```

### Button Group (tab-style filter)

```jsx
<div className="btn-group btn-group-sm">
  <button className="btn btn-secondary">Active tab</button>
  <button className="btn btn-outline-secondary">Inactive tab</button>
</div>
```

### Card header action buttons

```jsx
{/* Right side of card header */}
<Button variant="outline-secondary" className="me-2">
  <Icon icon="mdi:filter-outline" className="pe-1" /> Filter
</Button>
<Button variant="outline-primary">See All</Button>
```

---

## Forms

### Basic Form Field

```jsx
<div className="form-group mb-3">
  <label htmlFor="fieldId" className="form-label">Label</label>
  <input type="text" className="form-control" id="fieldId" placeholder="Placeholder" />
</div>
```

### Password Field

```jsx
<div className="form-group mb-3">
  <label htmlFor="password" className="form-label">Password</label>
  <input type="password" className="form-control" id="password" placeholder="Password" />
</div>
```

### Select

```jsx
<div className="form-group mb-3">
  <label htmlFor="example-select" className="form-label">Select</label>
  <select className="form-select" id="example-select">
    <option value="">Choose…</option>
    <option value="a">Option A</option>
    <option value="b">Option B</option>
  </select>
</div>
```

### Textarea

```jsx
<div className="form-group mb-3">
  <label htmlFor="textarea" className="form-label">Description</label>
  <textarea className="form-control" id="textarea" rows={4} placeholder="Enter text…" />
</div>
```

### Checkbox / Switch

```jsx
{/* Checkbox */}
<div className="form-check mb-2">
  <input className="form-check-input" type="checkbox" id="check1" />
  <label className="form-check-label" htmlFor="check1">Label</label>
</div>

{/* Toggle Switch */}
<div className="form-check form-switch mb-2">
  <input className="form-check-input" type="checkbox" id="switch1" />
  <label className="form-check-label" htmlFor="switch1">Enable feature</label>
</div>
```

### Input Group (with prefix/suffix)

```jsx
<div className="input-group mb-3">
  <span className="input-group-text">@</span>
  <input type="text" className="form-control" placeholder="Username" />
</div>

<div className="input-group mb-3">
  <input type="text" className="form-control" placeholder="Search…" />
  <button className="btn btn-outline-secondary" type="button">Go</button>
</div>
```

### Horizontal Form (label left, control right)

```jsx
<div className="form-group row mb-3">
  <label htmlFor="email" className="col-sm-3 col-form-label">Email</label>
  <div className="col-sm-9">
    <input type="email" className="form-control" id="email" placeholder="Email" />
  </div>
</div>
```

### Two-column Form Row

```jsx
<div className="row g-2">
  <div className="col-md-6 mb-3 form-group">
    <label className="form-label">First Name</label>
    <input type="text" className="form-control" />
  </div>
  <div className="col-md-6 mb-3 form-group">
    <label className="form-label">Last Name</label>
    <input type="text" className="form-control" />
  </div>
</div>
```

### Submit Button (always at bottom, left-aligned)

```jsx
<button type="button" className="btn btn-primary waves-effect waves-light">
  Save
</button>
```

### Form field spacing rules
- Each field wrapped in `div.form-group.mb-3`
- Labels use `form-label` (not `control-label`)
- `FormControl` / `form-control` for all text inputs
- `form-select` for dropdowns (not `form-control` on `<select>`)
- Help text: `<small className="help-block">…</small>` below the input
- Error text: `<div className="invalid-feedback">…</div>` (add `is-invalid` to input)

---

## Modals

### Standard Modal

```jsx
import { Modal, ModalHeader, ModalBody, ModalFooter, ModalTitle, Button } from 'react-bootstrap';

<Modal show={isOpen} onHide={handleClose}>
  <ModalHeader onHide={handleClose} closeButton>
    <ModalTitle as="h4">Modal Title</ModalTitle>
  </ModalHeader>
  <ModalBody>
    <p>Modal content here.</p>
  </ModalBody>
  <ModalFooter>
    <Button variant="light" onClick={handleClose}>Close</Button>
    <Button variant="primary" onClick={handleSave}>Save changes</Button>
  </ModalFooter>
</Modal>
```

### Modal sizes

```jsx
<Modal show={isOpen} size="lg" onHide={handleClose}>…</Modal>   {/* large */}
<Modal show={isOpen} size="sm" onHide={handleClose}>…</Modal>   {/* small */}
<Modal show={isOpen} dialogClassName="modal-full-width" onHide={handleClose}>…</Modal>
```

### Modal footer button convention
- Cancel/Close: `btn-light` (always leftmost)
- Primary action: `btn-primary` (rightmost)
- Destructive action: `btn-danger`

---

## Icons

Use `@iconify/react` with MDI icons. Import once:

```jsx
import { Icon } from '@iconify/react';
```

Common icons used in this project:

| Use case         | Icon                              |
|------------------|-----------------------------------|
| Dashboard        | `mdi:view-dashboard-outline`      |
| Tenants          | `mdi:office-building-outline`     |
| Plans            | `mdi:tag-outline`                 |
| Subscriptions    | `mdi:credit-card-outline`         |
| Settings         | `mdi:cog-outline`                 |
| Logs             | `mdi:text-box-outline`            |
| Menu manager     | `mdi:format-list-bulleted`        |
| Filter           | `mdi:filter-outline`              |
| Expand arrow     | `ri:arrow-drop-right-line`        |
| Generic item     | `mdi:circle-outline`              |

Usage:

```jsx
<Icon icon="mdi:cog-outline" />
<Icon icon="mdi:filter-outline" height={20} width={20} className="pe-1 lh-1" />
```

---

## Custom Overrides

Custom CSS lives in `assets/src/styles/custom/`. Each file has a single responsibility.
Import all custom files in `assets/src/main.jsx`. Vite merges everything into `app.css` at build.

### Current custom files

| File             | Purpose                                      |
|------------------|----------------------------------------------|
| `logo.css`       | Sidebar logo text styles (`.logo-lg-text`, `.logo-ai`) |
| `background.css` | Warm off-white page background (`--sarah-page-bg: #faf4f0`) |

### Background color variable

```css
/* Use this variable, not hardcoded hex */
var(--sarah-page-bg)   /* #faf4f0 — applied to body and .content-page */
```

### Do not override Techmin theme colors

The sidebar, topbar, and component colors come from `data-menu-color="dark"` and `data-topbar-color="light"` attributes set in `LayoutContext.jsx`. Do not add custom color overrides in `app.scss` — they conflict with the template's own theming system.

---

## Quick Reference Checklist

Before shipping a new page, verify:

- [ ] Page wrapped in `div.row > div.col-12`
- [ ] Card has `card-header` with `h4.card-title` + `p.text-muted.fw-semibold.mb-0`
- [ ] Action buttons are on the **right** side of card header
- [ ] Table has `table align-middle mb-0`
- [ ] `thead > tr` has `table-light text-capitalize`
- [ ] `card-body` has `p-0` if it contains a table
- [ ] Status badges use `bg-{color}-subtle text-{color}` (not solid)
- [ ] Form fields use `form-group mb-3` + `form-label` + `form-control`
- [ ] No hardcoded hex colors (use Bootstrap utilities or `--sarah-page-bg`)
- [ ] No custom SCSS added to `app.scss` for colors/layout
- [ ] Icons use `@iconify/react` with `mdi:` prefix
