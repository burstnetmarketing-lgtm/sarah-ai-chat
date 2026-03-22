import React, { useState } from 'react';
import { useMenuItems } from '../hooks/useMenuItems.js';
import MenuAccordion from '../components/menu/MenuAccordion.jsx';

export default function MenuManager() {
  const { parents, loading, saving, createParent, createChild, toggle, remove, moveUp, moveDown } = useMenuItems();
  const [form, setForm] = useState({ item_key: '', label: '', view_key: '' });

  function handleSubmit(e) {
    e.preventDefault();
    if (!form.item_key || !form.label || !form.view_key) return;
    createParent(form);
    setForm({ item_key: '', label: '', view_key: '' });
  }

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Menu Manager</h1>
        <p className="text-muted small mb-0">Manage sidebar navigation items and their children.</p>
      </div>

      <div className="card border-0 shadow-sm mb-4">
        <div className="card-header">
          <h6 className="mb-0 fw-semibold">Add Parent Menu Item</h6>
        </div>
        <div className="card-body">
          <form onSubmit={handleSubmit}>
            <div className="row g-3 align-items-end">
              <div className="col-md-4">
                <label className="form-label fw-semibold small">Item Key *</label>
                <input className="form-control form-control-sm" placeholder="my-feature"
                       value={form.item_key} onChange={e => setForm(f => ({ ...f, item_key: e.target.value }))} required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold small">Label *</label>
                <input className="form-control form-control-sm" placeholder="My Feature"
                       value={form.label} onChange={e => setForm(f => ({ ...f, label: e.target.value }))} required />
              </div>
              <div className="col-md-2">
                <label className="form-label fw-semibold small">View Key *</label>
                <input className="form-control form-control-sm" placeholder="my-feature"
                       value={form.view_key} onChange={e => setForm(f => ({ ...f, view_key: e.target.value }))} required />
              </div>
              <div className="col-md-2">
                <button type="submit" className="btn btn-primary btn-sm w-100" disabled={saving}>
                  {saving ? '...' : 'Add Parent'}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {loading ? (
        <p className="text-muted small">Loading...</p>
      ) : (
        <MenuAccordion parents={parents} saving={saving}
          onChildCreate={createChild} onToggle={toggle}
          onRemove={remove} onMoveUp={moveUp} onMoveDown={moveDown} />
      )}
    </>
  );
}
