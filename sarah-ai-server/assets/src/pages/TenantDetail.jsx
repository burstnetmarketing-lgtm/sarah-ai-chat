import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  getTenant,
  updateSiteStatus,
  listTenantUsers, addTenantUser, removeTenantUser,
  listAccountKeys, createAccountKey, deleteAccountKey,
  createSite,
  listSiteKeys, createSiteKey, deleteSiteKey,
  listAgents, assignAgent, listAvailableAgents,
  listKnowledge, createKnowledge, deleteKnowledge,
  markTenantSetupComplete,
} from '../api/provisioning.js';

// ─── Helpers ────────────────────────────────────────────────────────────────

function StatusBadge({ status, map }) {
  const colors = map ?? { active: 'success', inactive: 'secondary', suspended: 'warning', trialing: 'info', archived: 'dark' };
  return <span className={`badge bg-${colors[status] ?? 'secondary'}`}>{status ?? '—'}</span>;
}

function SectionHeader({ title, onRefresh, refreshing }) {
  return (
    <div className="d-flex justify-content-between align-items-center mb-2">
      <h6 className="fw-semibold mb-0">{title}</h6>
      {onRefresh && (
        <button className="btn btn-outline-secondary btn-sm" onClick={onRefresh} disabled={refreshing}>↺</button>
      )}
    </div>
  );
}

function Alert({ type, msg }) {
  if (!msg) return null;
  return <div className={`alert alert-${type} py-1 px-2 small mt-2 mb-0`}>{msg}</div>;
}

function PrereqCard({ msg }) {
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body text-center py-5 text-muted small">{msg}</div>
    </div>
  );
}

// ─── Readiness Stepper (clickable wizard nav) ─────────────────────────────────

function ReadinessCheck({ steps, activeStep, onStepClick }) {
  const completed  = steps.filter(s => s.ok).length;
  const percentage = completed * 10;
  const allOk      = percentage === 100;
  const nextIdx    = steps.findIndex(s => !s.ok);

  return (
    <div className="card shadow-sm mb-4">
      {/* Header */}
      <div className="card-header bg-dark text-white py-3">
        <div className="d-flex justify-content-between align-items-center">
          <div>
            <div className="fw-bold">Setup Progress</div>
            <div className="text-white-50 small mt-1">
              {allOk
                ? 'All steps complete — ready to launch'
                : `${10 - completed} step${10 - completed !== 1 ? 's' : ''} remaining`}
            </div>
          </div>
          <div className="text-end">
            <div className={`fw-bold fs-3 ${allOk ? 'text-success' : 'text-warning'}`}>
              {percentage}%
            </div>
            <div className="text-white-50 small">{completed} / 10 steps</div>
          </div>
        </div>
        <div className="progress mt-3" style={{ height: '5px' }}>
          <div
            className={`progress-bar ${allOk ? 'bg-success' : 'bg-warning'}`}
            style={{ width: `${percentage}%`, transition: 'width 0.6s ease' }}
          />
        </div>
      </div>

      {/* Step nodes */}
      <div className="card-body py-3 px-2">
        <div className="d-flex align-items-start">
          {steps.map((step, i) => {
            const done     = step.ok;
            const isActive = i === activeStep;
            const isNext   = i === nextIdx;
            const isLast   = i === 9;
            const canClick = done || i === 0 || steps[i - 1]?.ok;

            const nodeClass = done     ? 'bg-success border-success text-white'
                            : isActive ? 'bg-body border-warning text-warning'
                            : isNext   ? 'bg-body border-primary text-primary'
                            :            'bg-body border-secondary text-secondary';

            const labelClass = isActive ? 'text-warning fw-bold'
                             : done     ? 'text-success fw-bold'
                             : isNext   ? 'text-primary fw-semibold'
                             :            'text-muted';

            const lineClass  = done ? 'border-success' : 'border-secondary';

            return (
              <React.Fragment key={i}>
                <div
                  className={`d-flex flex-column align-items-center flex-shrink-0 ${!canClick ? 'opacity-50' : ''}`}
                  style={{ cursor: canClick ? 'pointer' : 'not-allowed' }}
                  onClick={() => { if (canClick) onStepClick(i); }}
                  title={`${step.label}: ${step.sub}`}
                >
                  <div
                    className={`rounded-circle border border-2 d-flex align-items-center justify-content-center fw-bold ${nodeClass}`}
                    style={{ width: '32px', height: '32px', fontSize: done ? '14px' : '11px', transition: 'all 0.25s' }}
                  >
                    {done ? '✓' : i + 1}
                  </div>
                  <div className="text-center mt-1" style={{ width: '52px' }}>
                    <div className={`${labelClass}`} style={{ fontSize: '10px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {step.label}
                    </div>
                    <div className="text-muted" style={{ fontSize: '9px', whiteSpace: 'nowrap' }}>
                      {step.sub}
                    </div>
                  </div>
                </div>

                {!isLast && (
                  <div
                    className={`flex-fill border-top border-2 ${lineClass}`}
                    style={{ marginTop: '15px', minWidth: '4px', transition: 'border-color 0.4s' }}
                  />
                )}
              </React.Fragment>
            );
          })}
        </div>
      </div>
    </div>
  );
}

// ─── Step 0: Tenant Info ──────────────────────────────────────────────────────

function TenantInfoPanel({ tenant }) {
  if (!tenant) return null;
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">Tenant</h6>
        <p className="text-muted small mb-0">The top-level account that owns all sites, keys, and users.</p>
      </div>
      <div className="card-body p-0">
        <table className="table table-sm mb-0">
          <tbody>
            <tr><td className="text-muted small ps-3 w-25">Name</td><td className="fw-semibold">{tenant.name}</td></tr>
            <tr><td className="text-muted small ps-3">Slug</td><td>{tenant.slug}</td></tr>
            <tr><td className="text-muted small ps-3">UUID</td><td className="text-muted small font-monospace">{tenant.uuid}</td></tr>
            <tr><td className="text-muted small ps-3">Status</td><td><StatusBadge status={tenant.status} /></td></tr>
            <tr><td className="text-muted small ps-3">Created</td><td className="text-muted small">{tenant.created_at}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── Step 1: Subscription Info ────────────────────────────────────────────────

function SubscriptionPanel({ subscription }) {
  if (!subscription) return (
    <div className="card border-0 shadow-sm">
      <div className="card-body text-center py-4">
        <p className="text-muted small mb-0">No subscription found. A trial is created automatically with the tenant.</p>
      </div>
    </div>
  );
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">Subscription</h6>
        <p className="text-muted small mb-0">Controls plan features, limits, and trial duration.</p>
      </div>
      <div className="card-body p-0">
        <table className="table table-sm mb-0">
          <tbody>
            <tr>
              <td className="text-muted small ps-3 w-25">Status</td>
              <td><StatusBadge status={subscription.status} map={{ trialing: 'info', active: 'success', expired: 'danger', cancelled: 'secondary' }} /></td>
            </tr>
            <tr><td className="text-muted small ps-3">Starts</td><td className="text-muted small">{subscription.starts_at ?? '—'}</td></tr>
            <tr><td className="text-muted small ps-3">Ends</td><td className="text-muted small">{subscription.ends_at ?? 'No expiry'}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─── Step 2: Users ────────────────────────────────────────────────────────────

function UsersSection({ tenantUuid, onReload }) {
  const [users, setUsers]     = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ username: '', email: '', password: '', send_welcome: false });
  const [saving, setSaving]   = useState(false);
  const [msg, setMsg]         = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listTenantUsers(tenantUuid)
      .then(res => { if (res.success) setUsers(res.data); })
      .finally(() => setLoading(false));
  }, [tenantUuid]);

  useEffect(() => { load(); }, [load]);

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.username || !form.email || !form.password) return;
    setSaving(true); setMsg(null);
    try {
      const res = await addTenantUser(tenantUuid, {
        username:     form.username,
        email:        form.email,
        password:     form.password,
        send_welcome: form.send_welcome,
      });
      if (res.success) {
        setForm({ username: '', email: '', password: '', send_welcome: false });
        load();
        onReload?.();
      } else {
        setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
      }
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleRemove(wpUserId) {
    if (!confirm('Remove this user from the tenant?')) return;
    try { await removeTenantUser(tenantUuid, wpUserId); load(); }
    catch { alert('Failed to remove user.'); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <SectionHeader title="Users" onRefresh={load} refreshing={loading} />
        <p className="text-muted small mb-0">Create a new WordPress user and attach them to this tenant.</p>
      </div>
      <div className="card-body">
        <form onSubmit={handleAdd} className="mb-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label small fw-semibold">Username *</label>
              <input type="text" className="form-control form-control-sm" placeholder="john_doe"
                value={form.username} onChange={e => setForm(f => ({ ...f, username: e.target.value }))} required />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">Email *</label>
              <input type="email" className="form-control form-control-sm" placeholder="john@example.com"
                value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">Password *</label>
              <input type="password" className="form-control form-control-sm" placeholder="••••••••"
                value={form.password} onChange={e => setForm(f => ({ ...f, password: e.target.value }))} required />
            </div>
            <div className="col-md-2 d-flex align-items-center pt-3">
              <div className="form-check">
                <input type="checkbox" className="form-check-input" id="send_welcome"
                  checked={form.send_welcome}
                  onChange={e => setForm(f => ({ ...f, send_welcome: e.target.checked }))} />
                <label className="form-check-label small" htmlFor="send_welcome">Welcome email</label>
              </div>
            </div>
            <div className="col-md-1">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                {saving ? '…' : 'Add'}
              </button>
            </div>
          </div>
          {msg && <Alert type={msg.type} msg={msg.text} />}
        </form>
        {loading ? <p className="text-muted small mb-0">Loading…</p> : users.length === 0 ? (
          <p className="text-muted small mb-0">No users yet.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Username</th><th>Email</th><th></th></tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.wp_user_id}>
                  <td>{u.user_login ?? '—'}</td>
                  <td className="text-muted small">{u.user_email ?? '—'}</td>
                  <td>
                    <button className="btn btn-sm btn-outline-danger py-0"
                      onClick={() => handleRemove(u.wp_user_id)}>Remove</button>
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

// ─── Step 3: Register Site ────────────────────────────────────────────────────

function SiteCreateSection({ tenantUuid, sites, onReload }) {
  const [form, setForm]     = useState({ name: '', url: '' });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg]       = useState(null);

  async function handleCreate(e) {
    e.preventDefault();
    if (!form.name.trim() || !form.url.trim()) return;
    setSaving(true); setMsg(null);
    try {
      const res = await createSite({ tenant_uuid: tenantUuid, name: form.name.trim(), url: form.url.trim() });
      if (res.success) { setForm({ name: '', url: '' }); onReload(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">Register Site</h6>
        <p className="text-muted small mb-0">A site is a client WordPress installation that Sarah AI will serve.</p>
      </div>
      <div className="card-body">
        <form onSubmit={handleCreate} className="mb-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Site Name *</label>
              <input className="form-control form-control-sm" placeholder="My Website"
                value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
            </div>
            <div className="col-md-5">
              <label className="form-label small fw-semibold">URL *</label>
              <input className="form-control form-control-sm" placeholder="https://example.com"
                value={form.url} onChange={e => setForm(f => ({ ...f, url: e.target.value }))} required />
            </div>
            <div className="col-md-3">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                {saving ? '…' : '+ Register Site'}
              </button>
            </div>
          </div>
          {msg && <Alert type={msg.type} msg={msg.text} />}
        </form>

        {sites.length === 0 ? (
          <p className="text-muted small mb-0">No sites registered yet.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Name</th><th>URL</th><th>Status</th></tr>
            </thead>
            <tbody>
              {sites.map(site => (
                <tr key={site.uuid ?? site.id}>
                  <td className="fw-semibold">{site.name}</td>
                  <td className="text-muted small">{site.url}</td>
                  <td><StatusBadge status={site.status} map={{ active: 'success', inactive: 'secondary', suspended: 'warning' }} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

// ─── Step 4: Site Status ──────────────────────────────────────────────────────

function SiteStatusPanel({ site, onReload }) {
  const [saving, setSaving] = useState(false);
  const [msg, setMsg]       = useState(null);

  async function handleSetStatus(status) {
    setSaving(true); setMsg(null);
    try {
      const res = await updateSiteStatus(site.uuid, status);
      if (res.success) { setMsg({ type: 'success', text: `Site is now ${status}.` }); onReload(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">Site Status — <span className="fw-normal text-muted">{site.name}</span></h6>
        <p className="text-muted small mb-0">Activate the site to make it operational for API access.</p>
      </div>
      <div className="card-body">
        <div className="d-flex align-items-center gap-3 mb-2">
          <span className="text-muted small">Current status:</span>
          <StatusBadge status={site.status} map={{ active: 'success', inactive: 'secondary', suspended: 'warning' }} />
        </div>
        {site.status !== 'active' ? (
          <button className="btn btn-success btn-sm" onClick={() => handleSetStatus('active')} disabled={saving}>
            {saving ? 'Activating…' : 'Activate Site'}
          </button>
        ) : (
          <div className="d-flex align-items-center gap-2">
            <span className="text-success small fw-semibold">Site is active and ready.</span>
            <button className="btn btn-outline-secondary btn-sm" onClick={() => handleSetStatus('inactive')} disabled={saving}>
              Deactivate
            </button>
          </div>
        )}
        {msg && <Alert type={msg.type} msg={msg.text} />}
      </div>
    </div>
  );
}

// ─── Step 5: Account Keys ─────────────────────────────────────────────────────

function AccountKeysSection({ tenantUuid, onReload }) {
  const [keys, setKeys]       = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ label: '' });
  const [saving, setSaving]   = useState(false);
  const [rawKey, setRawKey]   = useState(null);
  const [msg, setMsg]         = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listAccountKeys(tenantUuid)
      .then(res => { if (res.success) setKeys(res.data); })
      .finally(() => setLoading(false));
  }, [tenantUuid]);

  useEffect(() => { load(); }, [load]);

  async function handleIssue(e) {
    e.preventDefault();
    setSaving(true); setMsg(null); setRawKey(null);
    try {
      const res = await createAccountKey(tenantUuid, { label: form.label.trim() || 'default' });
      if (res.success) { setRawKey(res.data.raw_key); setForm({ label: '' }); load(); onReload?.(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleRevoke(uuid) {
    if (!confirm('Revoke this account key?')) return;
    try { await deleteAccountKey(uuid); load(); }
    catch { alert('Failed to revoke.'); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <SectionHeader title="Account Keys" onRefresh={load} refreshing={loading} />
        <p className="text-muted small mb-0">Account keys identify the tenant. Raw key shown only once.</p>
      </div>
      <div className="card-body">
        <form onSubmit={handleIssue} className="mb-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-8">
              <label className="form-label small fw-semibold">Label</label>
              <input className="form-control form-control-sm" placeholder="production"
                value={form.label} onChange={e => setForm({ label: e.target.value })} />
            </div>
            <div className="col-md-4">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                {saving ? '…' : 'Issue Key'}
              </button>
            </div>
          </div>
        </form>
        {rawKey && (
          <div className="alert alert-warning py-2 px-3 mb-3">
            <strong>Copy this key now — it will not be shown again.</strong>
            <div className="font-monospace mt-1 user-select-all small bg-body border rounded px-2 py-1">{rawKey}</div>
          </div>
        )}
        {msg && <Alert type={msg.type} msg={msg.text} />}
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
                    {k.status === 'active' && (
                      <button className="btn btn-sm btn-outline-danger py-0"
                        onClick={() => handleRevoke(k.uuid)}>Revoke</button>
                    )}
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

// ─── Step 6: Site Keys ────────────────────────────────────────────────────────

function SiteKeysSection({ siteUuid, onKeysChange }) {
  const [keys, setKeys]       = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ label: '' });
  const [saving, setSaving]   = useState(false);
  const [rawKey, setRawKey]   = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listSiteKeys(siteUuid)
      .then(res => { if (res.success) { setKeys(res.data); onKeysChange?.(res.data); } })
      .finally(() => setLoading(false));
  }, [siteUuid]);

  useEffect(() => { load(); }, [load]);

  async function handleIssue(e) {
    e.preventDefault();
    setSaving(true); setRawKey(null);
    try {
      const res = await createSiteKey(siteUuid, { label: form.label.trim() || 'default' });
      if (res.success) { setRawKey(res.data.raw_key); setForm({ label: '' }); load(); }
    } catch { alert('Request failed.'); }
    finally { setSaving(false); }
  }

  async function handleRevoke(uuid) {
    if (!confirm('Revoke this site key?')) return;
    try { await deleteSiteKey(uuid); load(); }
    catch { alert('Failed.'); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <SectionHeader title="Site Keys" onRefresh={load} refreshing={loading} />
        <p className="text-muted small mb-0">Site keys are the site-level credential for API authentication.</p>
      </div>
      <div className="card-body">
        <form onSubmit={handleIssue} className="mb-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-8">
              <label className="form-label small fw-semibold">Label</label>
              <input className="form-control form-control-sm" placeholder="default"
                value={form.label} onChange={e => setForm({ label: e.target.value })} />
            </div>
            <div className="col-md-4">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                {saving ? '…' : 'Issue Key'}
              </button>
            </div>
          </div>
        </form>
        {rawKey && (
          <div className="alert alert-warning py-2 px-3 mb-3">
            <strong>Copy now — shown once.</strong>
            <div className="font-monospace mt-1 user-select-all small bg-body border rounded px-2 py-1">{rawKey}</div>
          </div>
        )}
        {loading ? <p className="text-muted small mb-0">Loading…</p> : keys.length === 0 ? (
          <p className="text-muted small mb-0">No site keys issued.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Label</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              {keys.map(k => (
                <tr key={k.uuid ?? k.id}>
                  <td>{k.label}</td>
                  <td><StatusBadge status={k.status} map={{ active: 'success', revoked: 'danger' }} /></td>
                  <td>
                    {k.status === 'active' && (
                      <button className="btn btn-sm btn-outline-danger py-0"
                        onClick={() => handleRevoke(k.uuid)}>Revoke</button>
                    )}
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

// ─── Step 7: Agent ────────────────────────────────────────────────────────────

function AgentSection({ siteUuid, tenantUuid, currentAgentId, onAgentChange }) {
  const [agents, setAgents]     = useState([]);
  const [selected, setSelected] = useState(currentAgentId ?? '');
  const [saving, setSaving]     = useState(false);
  const [msg, setMsg]           = useState(null);

  useEffect(() => {
    const fetch = tenantUuid ? listAvailableAgents(tenantUuid) : listAgents();
    fetch.then(res => { if (res.success) setAgents(res.data); }).catch(() => {});
  }, [tenantUuid]);

  useEffect(() => { setSelected(currentAgentId ?? ''); }, [currentAgentId]);

  async function handleAssign(e) {
    e.preventDefault();
    if (!selected) return;
    setSaving(true); setMsg(null);
    try {
      const res = await assignAgent(siteUuid, parseInt(selected, 10));
      if (res.success) {
        setMsg({ type: 'success', text: 'Agent assigned.' });
        onAgentChange?.(parseInt(selected, 10));
      } else {
        setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
      }
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  const currentAgent = agents.find(a => a.id == currentAgentId);

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">
          Agent Assignment
          {currentAgent && <span className="badge bg-success ms-2">{currentAgent.name}</span>}
        </h6>
        <p className="text-muted small mb-0">Assign the AI agent that will handle chat for this site.</p>
      </div>
      <div className="card-body">
        <form onSubmit={handleAssign}>
          <div className="row g-2 align-items-end">
            <div className="col-md-8">
              <label className="form-label small fw-semibold">Agent</label>
              <select className="form-select form-select-sm"
                value={selected} onChange={e => setSelected(e.target.value)}>
                <option value="">— select agent —</option>
                {agents.map(a => (
                  <option key={a.id} value={a.id}>{a.name} ({a.slug})</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving || !selected}>
                {saving ? '…' : 'Assign'}
              </button>
            </div>
          </div>
          {msg && <Alert type={msg.type} msg={msg.text} />}
        </form>
      </div>
    </div>
  );
}

// ─── Step 8: Knowledge ────────────────────────────────────────────────────────

function KnowledgeSection({ siteUuid, onItemsChange }) {
  const [items, setItems]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [form, setForm]         = useState({ title: '', resource_type: 'text', source_content: '' });
  const [saving, setSaving]     = useState(false);
  const [msg, setMsg]           = useState(null);
  const [expanded, setExpanded] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    listKnowledge(siteUuid)
      .then(res => { if (res.success) { setItems(res.data); onItemsChange?.(res.data); } })
      .finally(() => setLoading(false));
  }, [siteUuid]);

  useEffect(() => { load(); }, [load]);

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.title.trim()) return;
    setSaving(true); setMsg(null);
    try {
      const res = await createKnowledge({
        site_uuid:      siteUuid,
        title:          form.title.trim(),
        resource_type:  form.resource_type,
        source_content: form.source_content,
      });
      if (res.success) { setForm({ title: '', resource_type: 'text', source_content: '' }); setExpanded(false); load(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleDelete(uuid) {
    if (!confirm('Delete this knowledge resource?')) return;
    try { await deleteKnowledge(uuid); load(); }
    catch { alert('Failed.'); }
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
              <div className="col-md-6">
                <input className="form-control form-control-sm" placeholder="Title *"
                  value={form.title} onChange={e => setForm(f => ({ ...f, title: e.target.value }))} required />
              </div>
              <div className="col-md-3">
                <input className="form-control form-control-sm" placeholder="Type (e.g. text)"
                  value={form.resource_type} onChange={e => setForm(f => ({ ...f, resource_type: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                  {saving ? '…' : 'Add'}
                </button>
              </div>
              <div className="col-12">
                <textarea className="form-control form-control-sm" rows={3} placeholder="Source content (optional)"
                  value={form.source_content} onChange={e => setForm(f => ({ ...f, source_content: e.target.value }))} />
              </div>
            </div>
            {msg && <Alert type={msg.type} msg={msg.text} />}
          </form>
        )}

        {loading ? <p className="text-muted small mb-0">Loading…</p> : items.length === 0 ? (
          <p className="text-muted small mb-0">No knowledge resources added yet.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Title</th><th>Type</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              {items.map(item => (
                <tr key={item.uuid ?? item.id}>
                  <td>{item.title}</td>
                  <td className="text-muted small">{item.resource_type}</td>
                  <td><StatusBadge status={item.status} /></td>
                  <td>
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

// ─── Step 9: Launch Panel ─────────────────────────────────────────────────────

function LaunchPanel({ allOk }) {
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body text-center py-5">
        {allOk ? (
          <>
            <div style={{ fontSize: '52px', color: '#198754', lineHeight: 1 }}>✓</div>
            <h5 className="fw-bold text-success mt-3 mb-1">All steps complete!</h5>
            <p className="text-muted small">This tenant is fully configured and ready for Phase 4.4 integration.</p>
            <span className="badge bg-success px-3 py-2">Ready to Launch</span>
          </>
        ) : (
          <>
            <div style={{ fontSize: '52px', color: '#dee2e6', lineHeight: 1 }}>○</div>
            <h6 className="text-muted mt-3 mb-1">Not ready yet</h6>
            <p className="text-muted small">Complete all previous steps to unlock launch readiness.</p>
          </>
        )}
      </div>
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function TenantDetail({ param, onNavigate }) {
  const tenantUuid = param;

  const [tenant, setTenant]           = useState(null);
  const [subscription, setSubscription] = useState(null);
  const [sites, setSites]             = useState([]);
  const [users, setUsers]             = useState([]);
  const [accountKeys, setAccountKeys] = useState([]);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState(null);
  const [activeStep, setActiveStep]   = useState(null);

  // First-site sub-data — updated as wizard steps mount
  const [firstSiteKeys, setFirstSiteKeys]               = useState([]);
  const [firstSiteAgentId, setFirstSiteAgentId]         = useState(null);
  const [firstSiteKnowledgeCount, setFirstSiteKnowledgeCount] = useState(0);

  const stepInitialized   = useRef(false);
  const setupMarkedRef    = useRef(false);
  const firstSite     = sites[0] ?? null;
  const firstSiteUuid = firstSite?.uuid ?? null;

  // Sync agent ID when sites array refreshes
  useEffect(() => {
    setFirstSiteAgentId(firstSite?.active_agent_id ?? null);
  }, [firstSite?.active_agent_id]);

  // Compute wizard steps
  const steps = [
    { label: 'Tenant',       sub: 'Active',      ok: !!tenant && tenant.status === 'active' },
    { label: 'Subscription', sub: 'Set',          ok: !!subscription },
    { label: 'User',         sub: 'Added',        ok: users.length > 0 },
    { label: 'Site',         sub: 'Registered',   ok: sites.length > 0 },
    { label: 'Site Status',  sub: 'Active',       ok: firstSite?.status === 'active' },
    { label: 'Account Key',  sub: 'Issued',       ok: accountKeys.length > 0 },
    { label: 'Site Key',     sub: 'Issued',       ok: firstSiteKeys.length > 0 },
    { label: 'Agent',        sub: 'Assigned',     ok: !!firstSiteAgentId },
    { label: 'Knowledge',    sub: 'Added',        ok: firstSiteKnowledgeCount > 0 },
    { label: 'Launch',       sub: 'Ready',        ok: false },
  ];
  steps[9].ok = steps.slice(0, 9).every(s => s.ok);

  const load = useCallback(async () => {
    if (!tenantUuid) return;
    setLoading(true);
    try {
      const [tenantRes, keysRes] = await Promise.all([
        getTenant(tenantUuid),
        listAccountKeys(tenantUuid),
      ]);

      if (!tenantRes.success) { setError('Tenant not found.'); return; }

      const loadedSites = tenantRes.data.sites ?? [];
      setTenant(tenantRes.data.tenant);
      setSubscription(tenantRes.data.subscription);
      setSites(loadedSites);
      setUsers(tenantRes.data.users ?? []);
      if (keysRes.success) setAccountKeys(keysRes.data);

      // Pre-load first-site sub-data so stepper reflects real state
      const fsuuid = loadedSites[0]?.uuid ?? null;
      if (fsuuid) {
        const [siteKeysRes, knowledgeRes] = await Promise.all([
          listSiteKeys(fsuuid),
          listKnowledge(fsuuid),
        ]);
        if (siteKeysRes.success) setFirstSiteKeys(siteKeysRes.data);
        if (knowledgeRes.success) setFirstSiteKnowledgeCount((knowledgeRes.data ?? []).length);
      }
    } catch {
      setError('Failed to load tenant.');
    } finally {
      setLoading(false);
    }
  }, [tenantUuid]);

  useEffect(() => { load(); }, [load]);

  // Auto-mark setup complete when all steps ok (fire-and-forget)
  const allOk = steps[9].ok;
  useEffect(() => {
    if (allOk && tenant && !tenant.setup_complete && !setupMarkedRef.current) {
      setupMarkedRef.current = true;
      markTenantSetupComplete(tenantUuid).catch(() => {});
    }
  }, [allOk, tenant]);

  // In setup mode: auto-navigate to first incomplete step on initial load
  const isEditMode = !!(tenant?.setup_complete || allOk);

  useEffect(() => {
    if (!loading && !stepInitialized.current && !isEditMode) {
      stepInitialized.current = true;
      const firstIncomplete = steps.findIndex(s => !s.ok);
      setActiveStep(firstIncomplete >= 0 ? firstIncomplete : 9);
    }
    if (!loading && !stepInitialized.current && isEditMode) {
      stepInitialized.current = true;
      setActiveStep(0);
    }
  }, [loading, isEditMode]);

  if (!tenantUuid) return <p className="text-danger">Invalid tenant.</p>;

  const STEP_TITLES = [
    'Tenant', 'Subscription', 'Users', 'Register Site',
    'Site Status', 'Account Keys', 'Site Keys', 'Agent', 'Knowledge', 'Launch',
  ];

  function renderStepContent() {
    if (loading || activeStep === null) return <p className="text-muted small">Loading…</p>;
    switch (activeStep) {
      case 0: return <TenantInfoPanel tenant={tenant} />;
      case 1: return <SubscriptionPanel subscription={subscription} />;
      case 2: return <UsersSection tenantUuid={tenantUuid} onReload={load} />;
      case 3: return <SiteCreateSection tenantUuid={tenantUuid} sites={sites} onReload={load} />;
      case 4: return firstSite
        ? <SiteStatusPanel site={firstSite} onReload={load} />
        : <PrereqCard msg="Register a site in Step 4 (Site) first." />;
      case 5: return <AccountKeysSection tenantUuid={tenantUuid} onReload={load} />;
      case 6: return firstSiteUuid
        ? <SiteKeysSection siteUuid={firstSiteUuid} onKeysChange={keys => setFirstSiteKeys(keys)} />
        : <PrereqCard msg="Register a site in Step 4 (Site) first." />;
      case 7: return firstSiteUuid
        ? <AgentSection
            siteUuid={firstSiteUuid}
            tenantUuid={tenantUuid}
            currentAgentId={firstSiteAgentId}
            onAgentChange={id => { setFirstSiteAgentId(id); load(); }}
          />
        : <PrereqCard msg="Register a site in Step 4 (Site) first." />;
      case 8: return firstSiteUuid
        ? <KnowledgeSection
            siteUuid={firstSiteUuid}
            onItemsChange={items => setFirstSiteKnowledgeCount(items.length)}
          />
        : <PrereqCard msg="Register a site in Step 4 (Site) first." />;
      case 9: return <LaunchPanel allOk={steps[9].ok} />;
      default: return null;
    }
  }

  return (
    <div className="row">
      <div className="col-12">

        {/* Page header */}
        <div className="mb-3 d-flex align-items-start justify-content-between">
          <div>
            {loading ? (
              <span className="text-muted small">Loading…</span>
            ) : error ? (
              <span className="text-danger">{error}</span>
            ) : (
              <>
                <h4 className="fw-semibold mb-0">{tenant?.name}</h4>
                <div className="mt-1 d-flex align-items-center gap-2 flex-wrap">
                  <span className="text-muted small">{tenant?.slug}</span>
                  <span className={`badge bg-${tenant?.status === 'active' ? 'success-subtle text-success' : 'secondary-subtle text-secondary'}`}>
                    {tenant?.status}
                  </span>
                  {subscription && (
                    <span className={`badge ${
                      subscription.status === 'trialing'  ? 'bg-primary-subtle text-primary' :
                      subscription.status === 'active'    ? 'bg-success-subtle text-success' :
                      subscription.status === 'cancelled' ? 'bg-danger-subtle text-danger' :
                                                            'bg-secondary-subtle text-secondary'
                    }`}>{subscription.status}</span>
                  )}
                  {isEditMode && (
                    <span className="badge bg-success-subtle text-success">
                      ✓ Setup Complete
                    </span>
                  )}
                </div>
              </>
            )}
          </div>
          <button className="btn btn-sm btn-outline-secondary"
            onClick={() => onNavigate('tenants')}>← Tenants</button>
        </div>

        {!loading && !error && (
          isEditMode ? (
            /* ── Edit Mode: Tabbed card ─────────────────────────── */
            <div className="card">
              <div className="card-header p-0 border-bottom-0">
                <ul className="nav nav-tabs card-header-tabs px-3 pt-2" style={{ flexWrap: 'nowrap', overflowX: 'auto' }}>
                  {STEP_TITLES.map((title, i) => (
                    <li key={i} className="nav-item" style={{ whiteSpace: 'nowrap' }}>
                      <button
                        className={`nav-link ${activeStep === i ? 'active' : ''}`}
                        onClick={() => setActiveStep(i)}
                      >
                        {title}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
              <div className="card-body">
                {renderStepContent()}
              </div>
            </div>
          ) : (
            /* ── Setup Mode: Stepper + Prev/Next ────────────────── */
            <>
              <ReadinessCheck steps={steps} activeStep={activeStep} onStepClick={setActiveStep} />

              {activeStep !== null && (
                <div className="d-flex align-items-center gap-2 mb-3">
                  <span className="badge bg-primary">Step {activeStep + 1}</span>
                  <span className="fw-semibold">{STEP_TITLES[activeStep]}</span>
                </div>
              )}

              {renderStepContent()}

              <div className="d-flex justify-content-between align-items-center mt-3 mb-4">
                <button
                  className="btn btn-outline-secondary btn-sm"
                  onClick={() => setActiveStep(s => Math.max(0, (s ?? 0) - 1))}
                  disabled={activeStep === 0}
                >
                  ← Previous
                </button>
                <div className="d-flex align-items-center gap-2">
                  {activeStep !== null && !steps[activeStep]?.ok && activeStep !== 9 && (
                    <span className="text-warning small fw-semibold">
                      Complete this step to continue
                    </span>
                  )}
                  <button
                    className="btn btn-primary btn-sm"
                    onClick={() => setActiveStep(s => Math.min(9, (s ?? 0) + 1))}
                    disabled={activeStep === 9 || (activeStep !== null && !steps[activeStep]?.ok)}
                  >
                    Next →
                  </button>
                </div>
              </div>
            </>
          )
        )}

      </div>
    </div>
  );
}
