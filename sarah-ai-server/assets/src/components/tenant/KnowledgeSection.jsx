import { useState, useEffect, useCallback } from 'react';
import {
  listKnowledge, createKnowledge, deleteKnowledge,
  processKnowledge, uploadKnowledgeFile, getKnowledgeResourceTypes,
  updateKnowledgeVisibility,
} from '../../api/provisioning.js';

const PROCESSING_LABELS = { none: 'None', queued: 'Queued', processing: 'Processing', done: 'Done', failed: 'Failed' };
import { Alert, StatusBadge } from './helpers.jsx';

const PROCESSING_COLORS = { none: 'secondary', queued: 'warning', processing: 'info', done: 'success', failed: 'danger' };
const EMPTY_FORM = { title: '', resource_type: 'text', source_content: '', file: null };

export default function KnowledgeSection({ siteUuid, onItemsChange }) {
  const [items, setItems]           = useState([]);
  const [loading, setLoading]       = useState(true);
  const [form, setForm]             = useState(EMPTY_FORM);
  const [saving, setSaving]         = useState(false);
  const [msg, setMsg]               = useState(null);
  const [expanded, setExpanded]     = useState(false);
  const [processing, setProcessing] = useState({});
  const [resourceTypes, setResourceTypes] = useState([]);

  useEffect(() => {
    getKnowledgeResourceTypes().then(res => { if (res.success) setResourceTypes(res.types); }).catch(() => {});
  }, []);

  const load = useCallback(() => {
    setLoading(true);
    listKnowledge(siteUuid)
      .then(res => { if (res.success) { setItems(res.data); onItemsChange?.(res.data); } })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [siteUuid]);

  useEffect(() => { load(); }, [load]);

  const isFileType = form.resource_type === 'pdf' || form.resource_type === 'docx';

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.title.trim() && !isFileType) return;
    setSaving(true); setMsg(null);
    try {
      let res;
      if (isFileType) {
        if (!form.file) { setMsg({ type: 'danger', text: 'Please select a file.' }); setSaving(false); return; }
        res = await uploadKnowledgeFile(siteUuid, form.file, form.title.trim());
      } else {
        res = await createKnowledge({
          site_uuid:      siteUuid,
          title:          form.title.trim(),
          resource_type:  form.resource_type,
          source_content: form.source_content,
        });
      }
      if (res.success) {
        setForm(EMPTY_FORM); setExpanded(false); load();
        // Server dispatches async processing; poll once after 5 s to reflect updated status.
        setTimeout(() => load(), 5000);
      } else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleDelete(uuid) {
    if (!confirm('Delete this knowledge resource?')) return;
    try { await deleteKnowledge(uuid); load(); }
    catch { alert('Failed.'); }
  }

  async function handleToggleVisibility(uuid, currentVisibility) {
    const next = currentVisibility === 'private' ? 'public' : 'private';
    try { await updateKnowledgeVisibility(uuid, next); load(); }
    catch { setMsg({ type: 'danger', text: 'Failed to update visibility.' }); }
  }

  async function handleProcess(uuid) {
    setProcessing(p => ({ ...p, [uuid]: true }));
    try {
      const res = await processKnowledge(uuid);
      if (!res.success) setMsg({ type: 'danger', text: 'Processing failed: ' + (res.message ?? 'Unknown error') });
      load();
    } catch (err) { setMsg({ type: 'danger', text: 'Processing request failed: ' + err.message }); }
    finally { setProcessing(p => ({ ...p, [uuid]: false })); }
  }

  function getDisplayTitle(item) {
    if (item.meta) {
      try {
        const meta = typeof item.meta === 'string' ? JSON.parse(item.meta) : item.meta;
        if (meta?.original_filename) return `${item.title} (${meta.original_filename})`;
      } catch {}
    }
    return item.title;
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <div className="d-flex justify-content-between align-items-center">
          <div>
            <h6 className="fw-semibold mb-0">
              Knowledge Resources
              <span className="badge bg-light text-dark border ms-2">{items.length}</span>
            </h6>
            <p className="text-muted small mb-0">Content the agent can use to answer questions for this site.</p>
          </div>
          <div className="d-flex gap-2">
            <button className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>↺</button>
            <button className="btn btn-outline-primary btn-sm" onClick={() => setExpanded(e => !e)}>
              {expanded ? '− Cancel' : '+ Add'}
            </button>
          </div>
        </div>
      </div>
      <div className="card-body">
        {expanded && (
          <form onSubmit={handleAdd} className="mb-3 p-3 bg-light rounded">
            <div className="row g-2">
              <div className="col-md-5">
                <input className="form-control form-control-sm" placeholder={isFileType ? 'Title (optional — uses filename if blank)' : 'Title *'}
                  value={form.title} onChange={e => setForm(f => ({ ...f, title: e.target.value }))}
                  required={!isFileType} />
              </div>
              <div className="col-md-4">
                <select className="form-select form-select-sm"
                  value={form.resource_type}
                  onChange={e => setForm(f => ({ ...f, resource_type: e.target.value, source_content: '', file: null }))}>
                  {resourceTypes.map(t => (
                    <option key={t.type_key} value={t.type_key}>{t.type_key} — {t.label}</option>
                  ))}
                </select>
              </div>
              <div className="col-md-3">
                <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                  {saving ? '…' : 'Add'}
                </button>
              </div>
              {form.resource_type === 'text' && (
                <div className="col-12">
                  <textarea className="form-control form-control-sm" rows={3} placeholder="Paste your content here *"
                    value={form.source_content} onChange={e => setForm(f => ({ ...f, source_content: e.target.value }))}
                    required />
                </div>
              )}
              {form.resource_type === 'link' && (
                <div className="col-12">
                  <input className="form-control form-control-sm" type="url" placeholder="https://example.com/page *"
                    value={form.source_content} onChange={e => setForm(f => ({ ...f, source_content: e.target.value }))}
                    required />
                </div>
              )}
              {isFileType && (
                <div className="col-12">
                  <input className="form-control form-control-sm" type="file"
                    accept={form.resource_type === 'pdf' ? '.pdf' : '.docx'}
                    onChange={e => setForm(f => ({ ...f, file: e.target.files[0] ?? null }))} />
                  {form.file && (
                    <div className="text-muted small mt-1">
                      {form.file.name} &nbsp;·&nbsp; {(form.file.size / 1024).toFixed(0)} KB
                    </div>
                  )}
                </div>
              )}
            </div>
            {msg && <Alert type={msg.type} msg={msg.text} />}
          </form>
        )}

        {loading ? <p className="text-muted small mb-0">Loading…</p> : items.length === 0 ? (
          <p className="text-muted small mb-0">No knowledge resources added yet.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Title</th><th>Type</th><th>Content</th><th>Visibility</th><th>Status</th><th>Processing</th><th></th></tr>
            </thead>
            <tbody>
              {items.map(item => (
                <tr key={item.uuid ?? item.id}>
                  <td>{getDisplayTitle(item)}</td>
                  <td className="text-muted small">{item.resource_type}</td>
                  <td className="text-muted small">
                    {item.resource_type === 'link'
                      ? <a href={item.source_content} target="_blank" rel="noreferrer" style={{ fontSize: '0.78rem' }}>link ↗</a>
                      : item.source_content
                          ? <span title={item.source_content}>{item.source_content.slice(0, 20)}{item.source_content.length > 20 ? '…' : ''}</span>
                          : <span className="text-secondary">—</span>
                    }
                  </td>
                  <td>
                    <button
                      className={`btn btn-sm py-0 ${item.visibility === 'private' ? 'btn-secondary' : 'btn-outline-success'}`}
                      onClick={() => handleToggleVisibility(item.uuid, item.visibility ?? 'public')}
                      title="Toggle public / private"
                      style={{ fontSize: '0.7rem' }}
                    >
                      {item.visibility === 'private' ? '🔒 private' : '🌐 public'}
                    </button>
                  </td>
                  <td><StatusBadge status={item.status} /></td>
                  <td>
                    <StatusBadge status={item.processing_status ?? 'none'} map={PROCESSING_COLORS} />
                    {item.processing_status === 'failed' && (
                      <div className="text-danger" style={{ fontSize: '0.7rem' }}>
                        {(() => { try { const m = typeof item.meta === 'string' ? JSON.parse(item.meta) : item.meta; return m?.processing_error || null; } catch { return null; } })()}
                      </div>
                    )}
                  </td>
                  <td className="text-end" style={{ whiteSpace: 'nowrap' }}>
                    <button
                      className={`btn btn-sm py-0 me-1 ${item.processing_status === 'failed' ? 'btn-danger' : 'btn-outline-primary'}`}
                      onClick={() => handleProcess(item.uuid)}
                      disabled={!!processing[item.uuid]}
                      title="Process / reprocess this resource"
                    >
                      {processing[item.uuid] ? '…' : item.processing_status === 'failed' ? '↺ Retry' : '⚙ Process'}
                    </button>
                    <button className="btn btn-sm btn-outline-danger py-0"
                      onClick={() => handleDelete(item.uuid)}>×</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
