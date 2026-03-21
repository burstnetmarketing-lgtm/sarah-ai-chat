# UI Guidelines

This project uses **Bootstrap 5** for all UI.
Admin pages run as a React SPA and have no connection to WordPress admin styles.

---

## Core Rule

> **Never use WordPress admin UI classes, elements, or patterns.**
> Everything is built with Bootstrap 5.

---

## Forbidden — Never Use

```html
<!-- ❌ WordPress table -->
<table class="wp-list-table widefat fixed striped">

<!-- ❌ WordPress form -->
<table class="form-table">
  <tr>
    <th><label>Name</label></th>
    <td><input class="regular-text" /></td>
  </tr>
</table>

<!-- ❌ WordPress notice -->
<div class="notice notice-success">

<!-- ❌ WordPress button -->
<button class="button button-primary">

<!-- ❌ WordPress metabox -->
<div id="poststuff"><div class="postbox">
```

---

## Correct — Always Use Bootstrap 5

```html
<!-- ✅ Bootstrap table -->
<table class="table table-striped table-hover">

<!-- ✅ Bootstrap form -->
<div class="mb-3">
  <label class="form-label">Name</label>
  <input class="form-control" />
</div>

<!-- ✅ Bootstrap alert -->
<div class="alert alert-success">

<!-- ✅ Bootstrap button -->
<button class="btn btn-primary">

<!-- ✅ Bootstrap card -->
<div class="card"><div class="card-body">
```

---

## Page Structure

```jsx
// ✅ Admin page layout
<div className="container-fluid p-4">
  <div className="row">
    <div className="col-12">
      <h1 className="h4 mb-4">Page Title</h1>
    </div>
  </div>
</div>

// ✅ Content card
<div className="card shadow-sm mb-4">
  <div className="card-header">
    <h5 className="card-title mb-0">Section</h5>
  </div>
  <div className="card-body">
    {/* content */}
  </div>
</div>
```

---

## Forms

```jsx
// ✅ Bootstrap form
<form>
  <div className="mb-3">
    <label className="form-label">Label</label>
    <input type="text" className="form-control" />
    <div className="form-text">Helper text</div>
  </div>

  <div className="mb-3">
    <label className="form-label">Select</label>
    <select className="form-select">
      <option>Option 1</option>
    </select>
  </div>

  <button type="submit" className="btn btn-primary">Save</button>
</form>
```

---

## Tables

```jsx
// ✅ Bootstrap table with actions
<div className="table-responsive">
  <table className="table table-hover align-middle">
    <thead className="table-light">
      <tr>
        <th>Name</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>...</td>
        <td><span className="badge bg-success">Active</span></td>
        <td>
          <button className="btn btn-sm btn-outline-primary">Edit</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## Alerts

```jsx
// ✅ Bootstrap alerts
<div className="alert alert-success">Saved successfully.</div>
<div className="alert alert-danger">An error occurred.</div>
<div className="alert alert-warning">Please review your input.</div>
```

---

## React Notes

- Always use `className`, never `class`
- Use inline styles only for dynamic values
- Place reusable components in `components/`
- Never use `wp.*` or `wpApiSettings` inside UI components — API calls belong in a separate layer
