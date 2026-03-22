import React, { useState, useEffect, useCallback } from 'react';
import {
  getTenant, updateTenantStatus,
  listTenantUsers, addTenantUser, removeTenantUser,
  listAccountKeys, createAccountKey, deleteAccountKey,
  createSite, getSite, updateSiteStatus,
  listSiteKeys, createSiteKey, deleteSiteKey,
  listAgents, assignAgent,
  listKnowledge, createKnowledge, deleteKnowledge,
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
        <button className="btn btn-outline-secondary btn-sm" onClick={onRefresh} disabled={refreshing}>
          ↺
        </button>
      )}
    </div>
  );
}

function Alert({ type, msg }) {
  if (!msg) return null;
  return <div className={`alert alert-${type} py-1 px-2 small mt-2 mb-0`}>{msg}</div>;
}

// ─── Readiness Check ─────────────────────────────────────────────────────────

function ReadinessCheck({ tenant, subscription, users, accountKeys, sites, siteDetails }) {
  const hasTenant      = !!tenant;
  const hasUser        = users.length > 0;
  const hasSite        = sites.length > 0;
  const hasAccountKey  = accountKeys.length > 0;

  const firstSiteId   = sites[0]?.id;
  const firstDetail   = firstSiteId ? siteDetails[firstSiteId] : null;
  const hasSiteKey    = (firstDetail?.site_keys?.length ?? 0) > 0;
  const hasAgent      = !!(firstDetail?.site?.active_agent_id);
  const hasKnowledge  = (firstDetail?.knowledge?.length ?? 0) > 0;
  const hasSub        = !!subscription;

  const checks = [
    { label: 'Tenant',      ok: hasTenant },
    { label: 'Subscription', ok: hasSub },
    { label: 'User',        ok: hasUser },
    { label: 'Site',        ok: hasSite },
    { label: 'Account Key', ok: hasAccountKey },
    { label: 'Site Key',    ok: hasSiteKey },
    { label: 'Agent',       ok: hasAgent },
    { label: 'Knowledge',   ok: hasKnowledge },
  ];

  const allOk = checks.every(c => c.ok);

  return (
    <div className={`card border-0 shadow-sm mb-4 border-start border-4 border-${allOk ? 'success' : 'warning'}`}>
      <div className="card-body py-2 px-3">
        <div className="d-flex flex-wrap gap-2 align-items-center">
          <span className="small fw-semibold text-muted me-1">Readiness:</span>
          {checks.map(c => (
            <span key={c.label}
              className={`badge ${c.ok ? 'bg-success' : 'bg-light text-secondary border'} px-2 py-1`}>
              {c.ok ? '✓' : '○'} {c.label}
            </span>
          ))}
          {allOk && <span className="badge bg-success ms-2">Ready for Phase 4.4</span>}
        </div>
      </div>
    </div>
  );
}

// ─── Users Section ───────────────────────────────────────────────────────────

function UsersSection({ tenantId }) {
  const [users, setUsers]       = useState([]);
  const [loading, setLoading]   = useState(true);
  const [form, setForm]         = useState({ wp_user_id: '', role: 'admin', send_welcome: false });
  const [saving, setSaving]     = useState(false);
  const [msg, setMsg]           = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listTenantUsers(tenantId)
      .then(res => { if (res.success) setUsers(res.data); })
      .finally(() => setLoading(false));
  }, [tenantId]);

  useEffect(() => { load(); }, [load]);

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.wp_user_id) return;
    setSaving(true); setMsg(null);
    try {
      const res = await addTenantUser(tenantId, {
        wp_user_id:   parseInt(form.wp_user_id, 10),
        role:         form.role,
        send_welcome: form.send_welcome,
      });
      if (res.success) { setForm({ wp_user_id: '', role: 'admin', send_welcome: false }); load(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleRemove(wpUserId) {
    if (!confirm('Remove this user association?')) return;
    try {
      await removeTenantUser(tenantId, wpUserId);
      load();
    } catch { alert('Failed to remove user.'); }
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header bg-white border-bottom">
        <SectionHeader title="Users" onRefresh={load} refreshing={loading} />
      </div>
      <div className="card-body">
        <form onSubmit={handleAdd} className="mb-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">WP User ID *</label>
              <input type="number" className="form-control form-control-sm" placeholder="1"
                value={form.wp_user_id} onChange={e => setForm(f => ({ ...f, wp_user_id: e.target.value }))} required />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">Role</label>
              <select className="form-select form-select-sm"
                value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))}>
                <option value="admin">admin</option>
                <option value="member">member</option>
                <option value="viewer">viewer</option>
              </select>
            </div>
            <div className="col-md-3 d-flex align-items-center pt-3">
              <div className="form-check">
                <input type="checkbox" className="form-check-input" id="send_welcome"
                  checked={form.send_welcome}
                  onChange={e => setForm(f => ({ ...f, send_welcome: e.target.checked }))} />
                <label className="form-check-label small" htmlFor="send_welcome">Send welcome email</label>
              </div>
            </div>
            <div className="col-md-2">
              <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                {saving ? '…' : 'Add'}
              </button>
            </div>
          </div>
          {msg && <Alert type={msg.type} msg={msg.text} />}
        </form>

        {loading ? <p className="text-muted small mb-0">Loading…</p> : users.length === 0 ? (
          <p className="text-muted small mb-0">No users associated.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>WP User ID</th><th>Login</th><th>Email</th><th>Role</th><th></th></tr>
            </thead>
            <tbody>
              {users.map(u => (
                <tr key={u.wp_user_id}>
                  <td>{u.wp_user_id}</td>
                  <td>{u.user_login ?? '—'}</td>
                  <td className="text-muted small">{u.user_email ?? '—'}</td>
                  <td><span className="badge bg-light text-dark border">{u.role}</span></td>
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

// ─── Account Keys Section ────────────────────────────────────────────────────

function AccountKeysSection({ tenantId }) {
  const [keys, setKeys]       = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ label: '' });
  const [saving, setSaving]   = useState(false);
  const [rawKey, setRawKey]   = useState(null);
  const [msg, setMsg]         = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listAccountKeys(tenantId)
      .then(res => { if (res.success) setKeys(res.data); })
      .finally(() => setLoading(false));
  }, [tenantId]);

  useEffect(() => { load(); }, [load]);

  async function handleIssue(e) {
    e.preventDefault();
    setSaving(true); setMsg(null); setRawKey(null);
    try {
      const res = await createAccountKey(tenantId, { label: form.label.trim() || 'default' });
      if (res.success) {
        setRawKey(res.data.raw_key);
        setForm({ label: '' });
        load();
      } else {
        setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
      }
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleRevoke(id) {
    if (!confirm('Revoke this account key?')) return;
    try { await deleteAccountKey(id); load(); }
    catch { alert('Failed to revoke.'); }
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header bg-white border-bottom">
        <SectionHeader title="Account Keys (tenant-level)" onRefresh={load} refreshing={loading} />
        <p className="text-muted small mb-0">Account keys identify the tenant. Raw key shown only once at issuance.</p>
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
            <div className="font-monospace mt-1 user-select-all small bg-white border rounded px-2 py-1">{rawKey}</div>
          </div>
        )}
        {msg && <Alert type={msg.type} msg={msg.text} />}

        {loading ? <p className="text-muted small mb-0">Loading…</p> : keys.length === 0 ? (
          <p className="text-muted small mb-0">No account keys issued.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>ID</th><th>Label</th><th>Status</th><th>Expires</th><th></th></tr>
            </thead>
            <tbody>
              {keys.map(k => (
                <tr key={k.id}>
                  <td className="text-muted small">{k.id}</td>
                  <td>{k.label}</td>
                  <td><StatusBadge status={k.status} map={{ active: 'success', revoked: 'danger' }} /></td>
                  <td className="text-muted small">{k.expires_at ?? 'never'}</td>
                  <td>
                    {k.status === 'active' && (
                      <button className="btn btn-sm btn-outline-danger py-0"
                        onClick={() => handleRevoke(k.id)}>Revoke</button>
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

// ─── Site Keys Sub-Section ───────────────────────────────────────────────────

function SiteKeysSection({ siteId, onKeysChange }) {
  const [keys, setKeys]       = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ label: '' });
  const [saving, setSaving]   = useState(false);
  const [rawKey, setRawKey]   = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listSiteKeys(siteId)
      .then(res => { if (res.success) { setKeys(res.data); onKeysChange?.(res.data); } })
      .finally(() => setLoading(false));
  }, [siteId]);

  useEffect(() => { load(); }, [load]);

  async function handleIssue(e) {
    e.preventDefault();
    setSaving(true); setRawKey(null);
    try {
      const res = await createSiteKey(siteId, { label: form.label.trim() || 'default' });
      if (res.success) { setRawKey(res.data.raw_key); setForm({ label: '' }); load(); }
    } catch { alert('Request failed.'); }
    finally { setSaving(false); }
  }

  async function handleRevoke(id) {
    if (!confirm('Revoke this site key?')) return;
    try { await deleteSiteKey(id); load(); }
    catch { alert('Failed.'); }
  }

  return (
    <div className="border-top pt-3 mt-3">
      <p className="small fw-semibold text-muted mb-2">Site Keys <span className="fw-normal text-secondary">(site credential)</span></p>
      <form onSubmit={handleIssue} className="mb-2">
        <div className="row g-2 align-items-end">
          <div className="col-8">
            <input className="form-control form-control-sm" placeholder="Label"
              value={form.label} onChange={e => setForm({ label: e.target.value })} />
          </div>
          <div className="col-4">
            <button className="btn btn-outline-primary btn-sm w-100" type="submit" disabled={saving}>
              {saving ? '…' : 'Issue'}
            </button>
          </div>
        </div>
      </form>
      {rawKey && (
        <div className="alert alert-warning py-1 px-2 small mb-2">
          <strong>Copy now — shown once:</strong>
          <div className="font-monospace user-select-all">{rawKey}</div>
        </div>
      )}
      {loading ? <p className="text-muted small">Loading…</p> : keys.length === 0 ? (
        <p className="text-muted small mb-0">No site keys.</p>
      ) : (
        <table className="table table-sm mb-0">
          <thead className="table-light">
            <tr><th>ID</th><th>Label</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {keys.map(k => (
              <tr key={k.id}>
                <td className="text-muted small">{k.id}</td>
                <td>{k.label}</td>
                <td><StatusBadge status={k.status} map={{ active: 'success', revoked: 'danger' }} /></td>
                <td>
                  {k.status === 'active' && (
                    <button className="btn btn-sm btn-outline-danger py-0"
                      onClick={() => handleRevoke(k.id)}>Revoke</button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

// ─── Agent Assignment Sub-Section ────────────────────────────────────────────

function AgentSection({ siteId, currentAgentId, onAgentChange }) {
  const [agents, setAgents]     = useState([]);
  const [selected, setSelected] = useState(currentAgentId ?? '');
  const [saving, setSaving]     = useState(false);
  const [msg, setMsg]           = useState(null);

  useEffect(() => {
    listAgents()
      .then(res => { if (res.success) setAgents(res.data); })
      .catch(() => {});
  }, []);

  useEffect(() => { setSelected(currentAgentId ?? ''); }, [currentAgentId]);

  async function handleAssign(e) {
    e.preventDefault();
    if (!selected) return;
    setSaving(true); setMsg(null);
    try {
      const res = await assignAgent(siteId, parseInt(selected, 10));
      if (res.success) { setMsg({ type: 'success', text: 'Agent assigned.' }); onAgentChange?.(parseInt(selected, 10)); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  const currentAgent = agents.find(a => a.id == currentAgentId);

  return (
    <div className="border-top pt-3 mt-3">
      <p className="small fw-semibold text-muted mb-2">
        Agent {currentAgent && <span className="badge bg-success ms-1">{currentAgent.name}</span>}
      </p>
      <form onSubmit={handleAssign}>
        <div className="row g-2 align-items-end">
          <div className="col-8">
            <select className="form-select form-select-sm"
              value={selected} onChange={e => setSelected(e.target.value)}>
              <option value="">— select agent —</option>
              {agents.map(a => (
                <option key={a.id} value={a.id}>{a.name} ({a.slug})</option>
              ))}
            </select>
          </div>
          <div className="col-4">
            <button className="btn btn-outline-primary btn-sm w-100" type="submit" disabled={saving || !selected}>
              {saving ? '…' : 'Assign'}
            </button>
          </div>
        </div>
        {msg && <Alert type={msg.type} msg={msg.text} />}
      </form>
    </div>
  );
}

// ─── Knowledge Sub-Section ───────────────────────────────────────────────────

function KnowledgeSection({ siteId }) {
  const [items, setItems]     = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ title: '', resource_type: 'text', source_content: '' });
  const [saving, setSaving]   = useState(false);
  const [msg, setMsg]         = useState(null);
  const [expanded, setExpanded] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    listKnowledge(siteId)
      .then(res => { if (res.success) setItems(res.data); })
      .finally(() => setLoading(false));
  }, [siteId]);

  useEffect(() => { load(); }, [load]);

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.title.trim()) return;
    setSaving(true); setMsg(null);
    try {
      const res = await createKnowledge({
        site_id:        parseInt(siteId, 10),
        title:          form.title.trim(),
        resource_type:  form.resource_type,
        source_content: form.source_content,
      });
      if (res.success) { setForm({ title: '', resource_type: 'text', source_content: '' }); setExpanded(false); load(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleDelete(id) {
    if (!confirm('Delete this knowledge resource?')) return;
    try { await deleteKnowledge(id); load(); }
    catch { alert('Failed.'); }
  }

  return (
    <div className="border-top pt-3 mt-3">
      <div className="d-flex justify-content-between align-items-center mb-2">
        <p className="small fw-semibold text-muted mb-0">
          Knowledge Resources <span className="badge bg-light text-dark border">{items.length}</span>
        </p>
        <button className="btn btn-sm btn-outline-secondary py-0"
          onClick={() => setExpanded(e => !e)}>
          {expanded ? '− Cancel' : '+ Add'}
        </button>
      </div>

      {expanded && (
        <form onSubmit={handleAdd} className="mb-2 p-2 bg-light rounded">
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

      {loading ? <p className="text-muted small">Loading…</p> : items.length === 0 ? (
        <p className="text-muted small mb-0">No knowledge resources.</p>
      ) : (
        <table className="table table-sm mb-0">
          <thead className="table-light">
            <tr><th>Title</th><th>Type</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            {items.map(item => (
              <tr key={item.id}>
                <td>{item.title}</td>
                <td className="text-muted small">{item.resource_type}</td>
                <td><StatusBadge status={item.status} /></td>
                <td>
                  <button className="btn btn-sm btn-outline-danger py-0"
                    onClick={() => handleDelete(item.id)}>×</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

// ─── Site Row ────────────────────────────────────────────────────────────────

function SiteRow({ site, onSiteDetailChange }) {
  const [open, setOpen]             = useState(false);
  const [agentId, setAgentId]       = useState(site.active_agent_id ?? null);
  const [siteKeys, setSiteKeys]     = useState([]);

  // Propagate detail changes up for readiness check
  useEffect(() => {
    onSiteDetailChange?.(site.id, { site, site_keys: siteKeys });
  }, [siteKeys, site]);

  return (
    <>
      <tr>
        <td className="text-muted small">{site.id}</td>
        <td className="fw-semibold">{site.name}</td>
        <td className="text-muted small">{site.url}</td>
        <td>
          <StatusBadge status={site.status} map={{ active: 'success', inactive: 'secondary', suspended: 'warning' }} />
        </td>
        <td>
          {agentId
            ? <span className="badge bg-success">agent #{agentId}</span>
            : <span className="badge bg-light text-secondary border">no agent</span>}
        </td>
        <td>
          <button className="btn btn-sm btn-outline-secondary py-0"
            onClick={() => setOpen(o => !o)}>
            {open ? '▲ Close' : '▼ Configure'}
          </button>
        </td>
      </tr>
      {open && (
        <tr>
          <td colSpan={6} className="bg-light">
            <div className="p-3">
              <SiteKeysSection
                siteId={site.id}
                onKeysChange={keys => setSiteKeys(keys)} />
              <AgentSection
                siteId={site.id}
                currentAgentId={agentId}
                onAgentChange={id => setAgentId(id)} />
              <KnowledgeSection siteId={site.id} />
            </div>
          </td>
        </tr>
      )}
    </>
  );
}

// ─── Sites Section ───────────────────────────────────────────────────────────

function SitesSection({ tenantId, sites, onReload, siteDetails, onSiteDetailChange }) {
  const [form, setForm]     = useState({ name: '', url: '' });
  const [saving, setSaving] = useState(false);
  const [msg, setMsg]       = useState(null);

  async function handleCreate(e) {
    e.preventDefault();
    if (!form.name.trim() || !form.url.trim()) return;
    setSaving(true); setMsg(null);
    try {
      const res = await createSite({ tenant_id: parseInt(tenantId, 10), name: form.name.trim(), url: form.url.trim() });
      if (res.success) { setForm({ name: '', url: '' }); onReload(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header bg-white border-bottom">
        <SectionHeader title="Sites" />
        <p className="text-muted small mb-0">Sites belong to this tenant. Each site has its own keys, agent, and knowledge.</p>
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
          <p className="text-muted small mb-0">No sites registered.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>ID</th><th>Name</th><th>URL</th><th>Status</th><th>Agent</th><th></th></tr>
            </thead>
            <tbody>
              {sites.map(site => (
                <SiteRow
                  key={site.id}
                  site={site}
                  onSiteDetailChange={onSiteDetailChange} />
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function TenantDetail({ param, onNavigate }) {
  const tenantId = parseInt(param, 10);

  const [tenant, setTenant]           = useState(null);
  const [subscription, setSubscription] = useState(null);
  const [sites, setSites]             = useState([]);
  const [users, setUsers]             = useState([]);
  const [accountKeys, setAccountKeys] = useState([]);
  const [siteDetails, setSiteDetails] = useState({});
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState(null);

  function handleSiteDetailChange(siteId, detail) {
    setSiteDetails(prev => ({ ...prev, [siteId]: detail }));
  }

  const load = useCallback(() => {
    if (!tenantId) return;
    setLoading(true);
    Promise.all([
      getTenant(tenantId),
      listAccountKeys(tenantId),
    ])
      .then(([tenantRes, keysRes]) => {
        if (tenantRes.success) {
          setTenant(tenantRes.data.tenant);
          setSubscription(tenantRes.data.subscription);
          setSites(tenantRes.data.sites ?? []);
          setUsers(tenantRes.data.users ?? []);
        } else {
          setError('Tenant not found.');
        }
        if (keysRes.success) setAccountKeys(keysRes.data);
      })
      .catch(() => setError('Failed to load tenant.'))
      .finally(() => setLoading(false));
  }, [tenantId]);

  useEffect(() => { load(); }, [load]);

  if (!tenantId) return <p className="text-danger">Invalid tenant ID.</p>;

  return (
    <>
      {/* Header */}
      <div className="mb-3 d-flex align-items-center gap-3">
        <button className="btn btn-sm btn-outline-secondary"
          onClick={() => onNavigate('tenants')}>← Tenants</button>
        {loading ? (
          <span className="text-muted small">Loading…</span>
        ) : error ? (
          <span className="text-danger">{error}</span>
        ) : (
          <div>
            <h1 className="h5 fw-semibold text-dark mb-0">{tenant?.name}</h1>
            <span className="text-muted small me-2">#{tenantId} · {tenant?.slug}</span>
            <StatusBadge status={tenant?.status} />
            {subscription && (
              <span className={`badge ms-2 bg-${subscription.status === 'trialing' ? 'info' : subscription.status === 'active' ? 'success' : 'secondary'}`}>
                {subscription.status}
              </span>
            )}
          </div>
        )}
      </div>

      {!loading && !error && (
        <>
          {/* Readiness */}
          <ReadinessCheck
            tenant={tenant}
            subscription={subscription}
            users={users}
            accountKeys={accountKeys}
            sites={sites}
            siteDetails={siteDetails} />

          {/* Users */}
          <UsersSection tenantId={tenantId} />

          {/* Account Keys */}
          <AccountKeysSection tenantId={tenantId} />

          {/* Sites */}
          <SitesSection
            tenantId={tenantId}
            sites={sites}
            onReload={load}
            siteDetails={siteDetails}
            onSiteDetailChange={handleSiteDetailChange} />
        </>
      )}
    </>
  );
}
