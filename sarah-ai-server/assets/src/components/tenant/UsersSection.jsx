import { useState, useEffect, useCallback } from 'react';
import { listTenantUsers, addTenantUser, removeTenantUser } from '../../api/provisioning.js';
import { SectionHeader, Alert } from './helpers.jsx';

export default function UsersSection({ tenantUuid, onReload }) {
  const [users, setUsers]     = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState({ username: '', email: '', password: '', send_welcome: false });
  const [saving, setSaving]   = useState(false);
  const [msg, setMsg]         = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    listTenantUsers(tenantUuid)
      .then(res => { if (res.success) setUsers(res.data); })
      .catch(() => {})
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
