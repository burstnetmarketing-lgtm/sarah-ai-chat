import React, { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '../api/client.js';

const STATUS_COLORS = {
  trialing:  'info',
  active:    'success',
  expired:   'secondary',
  cancelled: 'danger',
};

const ALL_STATUSES = ['', 'trialing', 'active', 'expired', 'cancelled'];
const STATUS_LABELS = { '': 'All', trialing: 'Trialing', active: 'Active', expired: 'Expired', cancelled: 'Cancelled' };

function formatDate(val) {
  if (!val) return '—';
  return new Date(val).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Subscriptions({ onNavigate }) {
  const [rows, setRows]         = useState([]);
  const [loading, setLoading]   = useState(true);
  const [filter, setFilter]     = useState('');
  const [search, setSearch]     = useState('');
  const [updating, setUpdating] = useState(null);
  const [msg, setMsg]           = useState(null);

  const load = useCallback(() => {
    setLoading(true);
    const qs = filter ? `?status=${filter}` : '';
    apiFetch(`subscriptions${qs}`)
      .then(res => { if (res.success) setRows(res.data); })
      .catch(() => setMsg({ type: 'danger', text: 'Failed to load subscriptions.' }))
      .finally(() => setLoading(false));
  }, [filter]);

  useEffect(() => { load(); }, [load]);

  async function handleStatusChange(sub, newStatus) {
    setUpdating(sub.id);
    try {
      const res = await apiFetch(`subscriptions/${sub.id}/status`, 'POST', { status: newStatus });
      if (res.success) load();
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch {
      setMsg({ type: 'danger', text: 'Request failed.' });
    } finally {
      setUpdating(null);
    }
  }

  const visible = rows.filter(r => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (r.tenant_name ?? '').toLowerCase().includes(q) ||
           (r.plan_name   ?? '').toLowerCase().includes(q);
  });

  return (
    <div className="p-4">
      {/* Header */}
      <div className="d-flex justify-content-between align-items-start mb-4">
        <div>
          <h4 className="fw-bold mb-0">Subscriptions</h4>
          <p className="text-muted small mb-0">All tenant subscriptions across all plans.</p>
        </div>
        <button className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>↺ Refresh</button>
      </div>

      {msg && (
        <div className={`alert alert-${msg.type} alert-dismissible py-2`}>
          {msg.text}
          <button className="btn-close" onClick={() => setMsg(null)} />
        </div>
      )}

      {/* Filters */}
      <div className="d-flex gap-2 mb-3 flex-wrap align-items-center">
        {/* Status tabs */}
        <div className="btn-group btn-group-sm">
          {ALL_STATUSES.map(s => (
            <button
              key={s}
              className={`btn ${filter === s ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => setFilter(s)}
            >
              {STATUS_LABELS[s]}
            </button>
          ))}
        </div>
        {/* Search */}
        <input
          type="text"
          className="form-control form-control-sm ms-auto"
          style={{ maxWidth: '220px' }}
          placeholder="Search tenant or plan…"
          value={search}
          onChange={e => setSearch(e.target.value)}
        />
      </div>

      {/* Table */}
      <div className="card border-0 shadow-sm">
        <div className="card-body p-0">
          {loading ? (
            <p className="text-muted p-3 mb-0">Loading…</p>
          ) : visible.length === 0 ? (
            <p className="text-muted p-3 mb-0">No subscriptions found.</p>
          ) : (
            <table className="table table-sm table-hover mb-0 align-middle">
              <thead className="table-light">
                <tr>
                  <th>Tenant</th>
                  <th>Plan</th>
                  <th>Status</th>
                  <th>Starts</th>
                  <th>Ends</th>
                  <th>Created</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {visible.map(sub => (
                  <tr key={sub.id}>
                    <td>
                      <button
                        className="btn btn-link btn-sm p-0 text-start text-decoration-none fw-semibold"
                        onClick={() => onNavigate?.('tenant-detail', sub.tenant_uuid)}
                      >
                        {sub.tenant_name ?? sub.tenant_slug ?? `#${sub.tenant_id}`}
                      </button>
                    </td>
                    <td className="text-muted small">{sub.plan_name ?? sub.plan_slug ?? `#${sub.plan_id}`}</td>
                    <td>
                      <span className={`badge bg-${STATUS_COLORS[sub.status] ?? 'secondary'}`}>
                        {sub.status}
                      </span>
                    </td>
                    <td className="text-muted small">{formatDate(sub.starts_at)}</td>
                    <td className="text-muted small">{formatDate(sub.ends_at)}</td>
                    <td className="text-muted small">{formatDate(sub.created_at)}</td>
                    <td>
                      <select
                        className="form-select form-select-sm"
                        style={{ width: '120px' }}
                        value={sub.status}
                        disabled={updating === sub.id}
                        onChange={e => handleStatusChange(sub, e.target.value)}
                      >
                        <option value="trialing">trialing</option>
                        <option value="active">active</option>
                        <option value="expired">expired</option>
                        <option value="cancelled">cancelled</option>
                      </select>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
        {!loading && (
          <div className="card-footer bg-white text-muted small py-2">
            {visible.length} subscription{visible.length !== 1 ? 's' : ''}
            {search && ` matching "${search}"`}
          </div>
        )}
      </div>
    </div>
  );
}
