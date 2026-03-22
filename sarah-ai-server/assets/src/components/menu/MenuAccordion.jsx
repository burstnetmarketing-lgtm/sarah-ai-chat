import React, { useState } from 'react';

export default function MenuAccordion({ parents, onChildCreate, onToggle, onRemove, onMoveUp, onMoveDown, saving }) {
  const [openKeys, setOpenKeys] = useState(() => new Set());

  function toggleOpen(key) {
    setOpenKeys(prev => {
      const next = new Set(prev);
      next.has(key) ? next.delete(key) : next.add(key);
      return next;
    });
  }

  if (parents.length === 0) return <p className="text-muted">No menu items yet.</p>;

  return (
    <div className="accordion">
      {parents.map(parent => (
        <AccordionItem key={parent.item_key} parent={parent}
          isOpen={openKeys.has(parent.item_key)}
          onToggleOpen={() => toggleOpen(parent.item_key)}
          onChildCreate={onChildCreate} onToggle={onToggle} onRemove={onRemove}
          onMoveUp={onMoveUp} onMoveDown={onMoveDown} saving={saving} />
      ))}
    </div>
  );
}

function AccordionItem({ parent, isOpen, onToggleOpen, onChildCreate, onToggle, onRemove, onMoveUp, onMoveDown, saving }) {
  const children      = parent.children || [];
  const isEnabled     = parent.is_enabled == 1;
  const isDeletable   = parent.is_deletable == 1;
  const allowChildren = parent.allow_children == 1;

  return (
    <div className="accordion-item border-0 shadow-sm rounded overflow-hidden mb-2">
      <h2 className="accordion-header">
        <button className={`accordion-button fw-semibold ${isOpen ? '' : 'collapsed'}`} type="button"
                onClick={onToggleOpen}>
          {parent.label}
          <code className="small text-muted fw-normal ms-2 me-auto">{parent.view_key}</code>
          <span className={`badge me-2 ${isEnabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}`}>
            {isEnabled ? 'Enabled' : 'Disabled'}
          </span>
          <span className="badge bg-light text-secondary border me-3">
            {children.length} child{children.length !== 1 ? 'ren' : ''}
          </span>
        </button>
      </h2>
      <div className={`accordion-collapse ${isOpen ? 'show' : 'collapse'}`}>
        <div className="accordion-body bg-light border-top">
          {children.length === 0 && <p className="text-muted small mb-3">No child items yet.</p>}
          {children.map((child, i) => (
            <ChildRow key={child.item_key} child={child}
              isFirst={i === 0} isLast={i === children.length - 1}
              onToggle={onToggle} onRemove={onRemove}
              onMoveUp={onMoveUp} onMoveDown={onMoveDown} saving={saving} />
          ))}
          {allowChildren && <AddChildForm parentKey={parent.item_key} onCreate={onChildCreate} saving={saving} />}
          <div className="d-flex gap-2 mt-3">
            <button className={`btn btn-sm ${isEnabled ? 'btn-outline-warning' : 'btn-outline-success'}`}
                    onClick={() => onToggle(parent.item_key)} disabled={saving}>
              {isEnabled ? 'Disable' : 'Enable'}
            </button>
            {isDeletable && (
              <button className="btn btn-sm btn-outline-danger"
                      onClick={() => onRemove(parent.item_key)} disabled={saving}>
                Delete Parent
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function ChildRow({ child, isFirst, isLast, onToggle, onRemove, onMoveUp, onMoveDown, saving }) {
  const isEnabled = child.is_enabled == 1;
  return (
    <div className="d-flex align-items-center gap-2 bg-body rounded border px-3 py-2 mb-2">
      <i className="bi bi-chevron-right text-muted small"></i>
      <span className="fw-semibold small flex-grow-1">{child.label}</span>
      <code className="small text-muted">{child.view_key}</code>
      <span className={`badge ${isEnabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}`}>
        {isEnabled ? 'Enabled' : 'Disabled'}
      </span>
      <button className="btn btn-outline-secondary btn-sm px-2"
              onClick={() => onMoveUp(child.item_key)} disabled={isFirst || saving}>↑</button>
      <button className="btn btn-outline-secondary btn-sm px-2"
              onClick={() => onMoveDown(child.item_key)} disabled={isLast || saving}>↓</button>
      <button className={`btn btn-sm ${isEnabled ? 'btn-outline-warning' : 'btn-outline-success'}`}
              onClick={() => onToggle(child.item_key)} disabled={saving}>
        {isEnabled ? 'Disable' : 'Enable'}
      </button>
      <button className="btn btn-sm btn-outline-danger"
              onClick={() => onRemove(child.item_key)} disabled={saving}>Delete</button>
    </div>
  );
}

function AddChildForm({ parentKey, onCreate, saving }) {
  const [form, setForm] = useState({ item_key: '', label: '', view_key: '' });

  function handleSubmit(e) {
    e.preventDefault();
    if (!form.item_key || !form.label || !form.view_key) return;
    onCreate({ ...form, parent_key: parentKey });
    setForm({ item_key: '', label: '', view_key: '' });
  }

  return (
    <form onSubmit={handleSubmit} className="bg-white rounded p-3 border mb-3">
      <p className="small fw-semibold mb-2">Add Child Item</p>
      <div className="row g-2 align-items-end">
        <div className="col">
          <input className="form-control form-control-sm" placeholder="Item Key *"
                 value={form.item_key} onChange={e => setForm(f => ({ ...f, item_key: e.target.value }))} required />
        </div>
        <div className="col">
          <input className="form-control form-control-sm" placeholder="Label *"
                 value={form.label} onChange={e => setForm(f => ({ ...f, label: e.target.value }))} required />
        </div>
        <div className="col">
          <input className="form-control form-control-sm" placeholder="View Key *"
                 value={form.view_key} onChange={e => setForm(f => ({ ...f, view_key: e.target.value }))} required />
        </div>
        <div className="col-auto">
          <button type="submit" className="btn btn-outline-primary btn-sm" disabled={saving}>Add</button>
        </div>
      </div>
    </form>
  );
}
