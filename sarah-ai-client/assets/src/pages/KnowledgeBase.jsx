import React, { useState, useEffect, useCallback } from 'react';
import {
  getKnowledgeResourceTypes,
  listKnowledgeResources,
  createKnowledgeResource,
  deleteKnowledgeResource,
  updateKnowledgeResourceStatus,
  processKnowledgeResource,
} from '../api/knowledgeApi.js';

const STATUS_BADGE = {
  active:     'bg-success',
  inactive:   'bg-secondary',
  pending:    'bg-warning text-dark',
  processing: 'bg-info text-dark',
  failed:     'bg-danger',
  archived:   'bg-dark',
};

function ResourceRow({ resource, onDelete, onToggle, onProcess }) {
  const [busy, setBusy] = useState(false);

  async function handleToggle() {
    setBusy(true);
    await onToggle(resource.uuid, resource.status === 'active' ? 'inactive' : 'active');
    setBusy(false);
  }

  async function handleProcess() {
    setBusy(true);
    await onProcess(resource.uuid);
    setBusy(false);
  }

  async function handleDelete() {
    if (!confirm(`Delete "${resource.title}"?`)) return;
    setBusy(true);
    await onDelete(resource.uuid);
    setBusy(false);
  }

  return (
    <tr style={{ opacity: busy ? 0.5 : 1 }}>
      <td>{resource.title || <span className="text-muted">—</span>}</td>
      <td className="text-muted">{resource.resource_type}</td>
      <td>
        <span className={`badge ${STATUS_BADGE[resource.status] ?? 'bg-secondary'}`}>
          {resource.status}
        </span>
      </td>
      <td className="text-muted">{resource.processing_status ?? '—'}</td>
      <td>
        <div className="d-flex gap-1">
          <button
            className="btn btn-sm btn-outline-secondary"
            onClick={handleToggle}
            disabled={busy}
            title={resource.status === 'active' ? 'Deactivate' : 'Activate'}
          >
            {resource.status === 'active' ? 'Deactivate' : 'Activate'}
          </button>
          <button
            className="btn btn-sm btn-outline-primary"
            onClick={handleProcess}
            disabled={busy}
            title="Run processing pipeline"
          >
            Process
          </button>
          <button
            className="btn btn-sm btn-outline-danger"
            onClick={handleDelete}
            disabled={busy}
          >
            Delete
          </button>
        </div>
      </td>
    </tr>
  );
}

function AddResourceForm({ types, onAdd, onCancel }) {
  const [form, setForm]     = useState({ resource_type: types[0]?.type_key ?? '', title: '', source_content: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState('');

  const isLink = form.resource_type === 'link';

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.resource_type || !form.source_content) {
      setError('Type and content are required.');
      return;
    }
    setSaving(true);
    setError('');
    try {
      await onAdd(form);
      setForm({ resource_type: types[0]?.type_key ?? '', title: '', source_content: '' });
      onCancel();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="border rounded p-3 bg-light mb-3">
      {error && <div className="alert alert-danger py-1 px-2 small mb-2">{error}</div>}
      <div className="row g-2 mb-2">
        <div className="col-md-3">
          <select
            className="form-select form-select-sm"
            name="resource_type"
            value={form.resource_type}
            onChange={handleChange}
            disabled={saving}
          >
            <option value="">— Type —</option>
            {types.map(t => <option key={t.type_key} value={t.type_key}>{t.label}</option>)}
          </select>
        </div>
        <div className="col-md-9">
          <input
            type="text"
            className="form-control form-control-sm"
            name="title"
            value={form.title}
            onChange={handleChange}
            placeholder="Title"
            disabled={saving}
          />
        </div>
        <div className="col-12">
          {isLink ? (
            <input
              type="url"
              className="form-control form-control-sm"
              name="source_content"
              value={form.source_content}
              onChange={handleChange}
              placeholder="https://example.com"
              disabled={saving}
              required
            />
          ) : (
            <textarea
              className="form-control form-control-sm"
              name="source_content"
              value={form.source_content}
              onChange={handleChange}
              placeholder="Paste your text content here…"
              disabled={saving}
              rows={5}
              required
            />
          )}
        </div>
      </div>
      <div className="d-flex justify-content-end gap-2">
        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onCancel} disabled={saving}>
          Cancel
        </button>
        <button type="submit" className="btn btn-sm btn-primary orange-bg" disabled={saving}>
          {saving ? 'Adding…' : 'Add Resource'}
        </button>
      </div>
    </form>
  );
}

export default function KnowledgeBase() {
  const [resources, setResources]   = useState([]);
  const [types, setTypes]           = useState([]);
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState('');
  const [showForm, setShowForm]     = useState(false);

  const load = useCallback(async () => {
    setError('');
    try {
      const [resData, typesData] = await Promise.all([
        listKnowledgeResources(),
        getKnowledgeResourceTypes(),
      ]);
      setResources(resData.data ?? []);
      setTypes(typesData.types ?? []);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleAdd(fields) {
    await createKnowledgeResource(fields);
    await load();
  }

  async function handleDelete(uuid) {
    await deleteKnowledgeResource(uuid);
    setResources(prev => prev.filter(r => r.uuid !== uuid));
  }

  async function handleToggle(uuid, status) {
    await updateKnowledgeResourceStatus(uuid, status);
    await load();
  }

  async function handleProcess(uuid) {
    await processKnowledgeResource(uuid);
    await load();
  }

  return (
    <>
      <div className="mb-3 d-flex align-items-start justify-content-between">
        <div>
          <h1 className="h5 fw-semibold text-dark mb-1">Knowledge Base</h1>
          <p className="text-muted small mb-0">Manage the AI knowledge resources for this site.</p>
        </div>
        <button className="btn btn-sm btn-primary orange-bg" onClick={() => setShowForm(f => !f)}>
          {showForm ? '− Cancel' : '+ Add Resource'}
        </button>
      </div>

      {error && (
        <div className="alert alert-danger py-2 px-3 small mb-3">
          {error.includes('platform_key') || error.includes('Authentication')
            ? <>Authentication failed — check your <strong>Platform Key</strong> in Settings.</>
            : error}
        </div>
      )}

      {showForm && <AddResourceForm types={types} onAdd={handleAdd} onCancel={() => setShowForm(false)} />}

      <div className="card border-0 shadow-sm">
        <div className="card-body p-0">
          {loading ? (
            <p className="text-muted small p-3">Loading…</p>
          ) : resources.length === 0 ? (
            <p className="text-muted small p-3">No knowledge resources yet.</p>
          ) : (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr>
                  <th>Title</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Processing</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {resources.map(r => (
                  <ResourceRow
                    key={r.uuid}
                    resource={r}
                    onDelete={handleDelete}
                    onToggle={handleToggle}
                    onProcess={handleProcess}
                  />
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  );
}
