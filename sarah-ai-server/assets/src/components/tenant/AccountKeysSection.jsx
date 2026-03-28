import { useState, useEffect, useCallback } from 'react';
import { listAccountKeys, createAccountKey, deleteAccountKey } from '../../api/provisioning.js';
import { Alert, StatusBadge } from './helpers.jsx';

export default function AccountKeysSection({ tenantUuid, onKeysChange }) {
  const [keys, setKeys]         = useState([]);
  const [loading, setLoading]   = useState(true);
  const [expanded, setExpanded] = useState(false);
  const [form, setForm]         = useState({ label: '' });
  const [saving, setSaving]     = useState(false);
  const [rawKey, setRawKey]     = useState(null);
  const [msg, setMsg]           = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listAccountKeys(tenantUuid)
      .then(res => { if (res.success) { setKeys(res.data); onKeysChange?.(res.data); } })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [tenantUuid]);

  useEffect(() => { load(); }, [load]);

  async function handleIssue(e) {
    e.preventDefault();
    setSaving(true); setMsg(null); setRawKey(null);
    try {
      const res = await createAccountKey(tenantUuid, { label: form.label.trim() || 'default' });
      if (res.success) { setRawKey(res.data.raw_key); setForm({ label: '' }); setExpanded(false); load(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleRevoke(uuid) {
    if (!confirm('Revoke this account key?')) return;
    try { await deleteAccountKey(uuid); load(); }
    catch { alert('Failed to revoke.'); }
  }

  async function handleRegenerate(key) {
    if (!confirm('This will revoke the current key and issue a new one. Continue?')) return;
    setSaving(true); setMsg(null); setRawKey(null);
    try {
      await deleteAccountKey(key.uuid);
      const res = await createAccountKey(tenantUuid, { label: key.label || 'default' });
      if (res.success) { setRawKey(res.data.raw_key); load(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed to regenerate.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <div className="d-flex justify-content-between align-items-center">
          <div>
            <h6 className="fw-semibold mb-0">
              Account Keys
              <span className="badge bg-light text-dark border ms-2">{keys.length}</span>
            </h6>
            <p className="text-muted small mb-0">Account keys identify the tenant. Raw key shown only once.</p>
          </div>
          <div className="d-flex gap-2">
            <button className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>↺</button>
            <button className="btn btn-outline-primary btn-sm" onClick={() => { setExpanded(e => !e); setMsg(null); }}>
              {expanded ? '− Cancel' : '+ Issue Key'}
            </button>
          </div>
        </div>
      </div>
      <div className="card-body">
        {expanded && (
          <form onSubmit={handleIssue} className="mb-3 p-3 bg-light rounded">
            <div className="row g-2 align-items-end">
              <div className="col-md-10">
                <input className="form-control form-control-sm" placeholder="Label (default: production)"
                  value={form.label} onChange={e => setForm({ label: e.target.value })} disabled={saving} />
              </div>
              <div className="col-md-2">
                <button className="btn btn-primary btn-sm w-100 orange-bg" type="submit" disabled={saving}>
                  {saving ? '…' : 'Issue Key'}
                </button>
              </div>
            </div>
            {msg && <Alert type={msg.type} msg={msg.text} />}
          </form>
        )}

        {rawKey && (
          <div className="alert alert-warning py-2 px-3 mb-3">
            <strong>Copy this key now — it will not be shown again.</strong>
            <div className="font-monospace mt-1 user-select-all small bg-body border rounded px-2 py-1">{rawKey}</div>
          </div>
        )}

        {loading ? <p className="text-muted small mb-0">Loading…</p> : keys.length === 0 ? (
          <p className="text-muted small mb-0">No account keys issued.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Label</th><th>Status</th><th>Expires</th><th></th></tr>
            </thead>
            <tbody>
              {keys.map(k => (
                <tr key={k.uuid ?? k.id}>
                  <td>{k.label}</td>
                  <td><StatusBadge status={k.status} map={{ active: 'success', revoked: 'danger' }} /></td>
                  <td className="text-muted small">{k.expires_at ?? 'never'}</td>
                  <td>
                    <div className="d-flex gap-1 justify-content-end">
                      {k.status === 'active' && (
                        <>
                          <button className="btn btn-sm btn-outline-secondary py-0"
                            onClick={() => handleRegenerate(k)} disabled={saving}>Regenerate</button>
                          <button className="btn btn-sm btn-outline-danger py-0"
                            onClick={() => handleRevoke(k.uuid)}>Revoke</button>
                        </>
                      )}
                    </div>
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
