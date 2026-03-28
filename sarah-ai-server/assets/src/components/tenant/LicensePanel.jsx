import { useState } from 'react';

export default function LicensePanel({ tenant, tenantUuid, onReload }) {
  const [key, setKey]       = useState('');
  const [saving, setSaving] = useState(false);
  const [msg, setMsg]       = useState(null);

  const hasKey = !!(tenant?.whmcs_key);

  async function handleSave(e) {
    e.preventDefault();
    setSaving(true); setMsg(null);
    try {
      const res = await fetch(
        `${window.SarahAiServerConfig?.apiBase ?? ''}/tenants/${tenantUuid}/whmcs-key`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.SarahAiServerConfig?.nonce ?? '' },
          body: JSON.stringify({ whmcs_key: key }),
        }
      ).then(r => r.json());
      if (res.success) { setKey(''); onReload?.(); setMsg({ type: 'success', text: 'WHMCS key saved.' }); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">License</h6>
        <p className="text-muted small mb-0">Tenant's WHMCS license key. If set, all sites under this tenant are on the Customer plan.</p>
      </div>
      <div className="card-body">
        <div className="mb-3">
          <div className="fw-semibold small mb-1">Current Status</div>
          {hasKey
            ? <span className="badge bg-success-subtle text-success">Customer — WHMCS key set</span>
            : <span className="badge bg-info-subtle text-info">Trial — no WHMCS key</span>
          }
        </div>
        <form onSubmit={handleSave}>
          <div className="mb-2">
            <label className="form-label fw-semibold small">{hasKey ? 'Update WHMCS Key' : 'Set WHMCS Key'}</label>
            <input
              type="password"
              className="form-control form-control-sm font-monospace"
              value={key}
              onChange={e => setKey(e.target.value)}
              placeholder="Enter WHMCS license key"
              autoComplete="new-password"
              disabled={saving}
            />
          </div>
          <button type="submit" className="btn btn-primary orange-bg px-4" disabled={saving || key === ''}>
            {saving ? 'Saving…' : 'Save Key'}
          </button>
          {hasKey && (
            <button type="button" className="btn btn-sm btn-outline-danger ms-2"
              disabled={saving}
              onClick={() => { setKey(''); handleSave({ preventDefault: () => {} }); }}>
              Remove Key
            </button>
          )}
          {msg && <div className={`alert alert-${msg.type} py-1 px-2 small mt-2 mb-0`}>{msg.text}</div>}
        </form>
      </div>
    </div>
  );
}
