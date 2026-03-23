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
      <td className="small">{resource.title || <span className="text-muted">—</span>}</td>
      <td className="small text-muted">{resource.resource_type}</td>
      <td>
        <span className={`badge ${STATUS_BADGE[resource.status] ?? 'bg-secondary'}`} style={{ fontSize: '0.65rem' }}>
          {resource.status}
        </span>
      </td>
      <td className="small text-muted">{resource.processing_status ?? '—'}</td>
      <td>
        <div className="d-flex gap-1">
          <button
            className="btn btn-xs btn-outline-secondary"
            style={{ fontSize: '0.7rem', padding: '1px 6px' }}
            onClick={handleToggle}
            disabled={busy}
            title={resource.status === 'active' ? 'Deactivate' : 'Activate'}
          >
            {resource.status === 'active' ? 'Deactivate' : 'Activate'}
          </button>
          <button
            className="btn btn-xs btn-outline-primary"
            style={{ fontSize: '0.7rem', padding: '1px 6px' }}
            onClick={handleProcess}
            disabled={busy}
            title="Run processing pipeline"
          >
            Process
          </button>
          <button
            className="btn btn-xs btn-outline-danger"
            style={{ fontSize: '0.7rem', padding: '1px 6px' }}
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

function AddResourceForm({ types, onAdd }) {
  const [form, setForm]   = useState({ resource_type: '', title: '', source_content: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError]   = useState('');

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
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="border rounded p-3 bg-light mb-3">
      <div className="fw-semibold small mb-2">Add Knowledge Resource</div>
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
        <div className="col-md-4">
          <input
            type="text"
            className="form-control form-control-sm"
            name="title"
            value={form.title}
            onChange={handleChange}
            placeholder="Title (optional)"
            disabled={saving}
          />
        </div>
        <div className="col-md-5">
          <input
            type="text"
            className="form-control form-control-sm"
            name="source_content"
            value={form.source_content}
            onChange={handleChange}
            placeholder="Content or URL"
            disabled={saving}
          />
        </div>
      </div>
      <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
        {saving ? 'Adding…' : 'Add Resource'}
      </button>
    </form>
  );
}

export default function KnowledgeBase() {
  const [resources, setResources] = useState([]);
  const [types, setTypes]         = useState([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState('');

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
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Knowledge Base</h1>
        <p className="text-muted small mb-0">Manage the AI knowledge resources for this site.</p>
      </div>

      {error && (
        <div className="alert alert-danger py-2 px-3 small mb-3">
          {error.includes('platform_key') || error.includes('Authentication')
            ? <>Authentication failed — check your <strong>Platform Key</strong> in Settings.</>
            : error}
        </div>
      )}

      <AddResourceForm types={types} onAdd={handleAdd} />

      <div className="card border-0 shadow-sm">
        <div className="card-body p-0">
          {loading ? (
            <p className="text-muted small p-3">Loading…</p>
          ) : resources.length === 0 ? (
            <p className="text-muted small p-3">No knowledge resources yet.</p>
          ) : (
            <table className="table table-sm table-hover mb-0" style={{ fontSize: '0.82rem' }}>
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
