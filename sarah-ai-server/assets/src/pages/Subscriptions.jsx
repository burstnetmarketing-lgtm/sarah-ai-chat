import React, { useState, useEffect, useCallback } from 'react';
import { apiFetch } from '../api/client.js';

const STATUS_BADGE = {
  trialing:  'bg-primary-subtle text-primary',
  active:    'bg-success-subtle text-success',
  expired:   'bg-secondary-subtle text-secondary',
  cancelled: 'bg-danger-subtle text-danger',
};

const STATUS_OPTIONS = ['', 'trialing', 'active', 'expired', 'cancelled'];
const STATUS_LABELS  = { '': 'All', trialing: 'Trialing', active: 'Active', expired: 'Expired', cancelled: 'Cancelled' };

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

  const load = useCallback(() => {
    setLoading(true);
    const qs = filter ? `?status=${filter}` : '';
    apiFetch(`subscriptions${qs}`)
      .then(res => { if (res.success) setRows(res.data); })
      .finally(() => setLoading(false));
  }, [filter]);

  useEffect(() => { load(); }, [load]);

  async function handleStatusChange(sub, newStatus) {
    setUpdating(sub.id);
    try {
      await apiFetch(`subscriptions/${sub.id}/status`, 'POST', { status: newStatus });
      load();
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
    <div className="row">
      <div className="col-12">
        <div className="card">

          {/* Card Header */}
          <div className="d-flex justify-content-between flex-wrap align-items-center card-header">
            <div>
              <h4 className="card-title">Subscriptions</h4>
              <p className="text-muted fw-semibold mb-0">All tenant subscriptions across all plans.</p>
            </div>
            <div className="d-flex align-items-center gap-2 flex-wrap">
              {/* Status filter buttons */}
              <div className="btn-group btn-group-sm">
                {STATUS_OPTIONS.map(s => (
                  <button
                    key={s}
                    type="button"
                    className={`btn ${filter === s ? 'btn-secondary' : 'btn-outline-secondary'}`}
                    onClick={() => setFilter(s)}
                  >
                    {STATUS_LABELS[s]}
                  </button>
                ))}
              </div>
              {/* Search */}
              <input
                type="text"
                className="form-control form-control-sm"
                style={{ width: '180px' }}
                placeholder="Search tenant or plan…"
                value={search}
                onChange={e => setSearch(e.target.value)}
              />
              {/* Refresh */}
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>
                ↺
              </button>
            </div>
          </div>

          {/* Card Body */}
          <div className="p-0 card-body">
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead>
                  <tr className="table-light text-capitalize">
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
                  {loading ? (
                    <tr><td colSpan={7} className="text-muted py-4 text-center">Loading…</td></tr>
                  ) : visible.length === 0 ? (
                    <tr><td colSpan={7} className="text-muted py-4 text-center">No subscriptions found.</td></tr>
                  ) : visible.map(sub => (
                    <tr key={sub.id}>
                      <td>
                        <button
                          className="btn btn-link p-0 text-start fw-semibold"
                          onClick={() => onNavigate?.('tenant-detail', sub.tenant_uuid)}
                        >
                          {sub.tenant_name ?? sub.tenant_slug ?? `#${sub.tenant_id}`}
                        </button>
                      </td>
                      <td>{sub.plan_name ?? sub.plan_slug ?? `#${sub.plan_id}`}</td>
                      <td>
                        <span className={`badge ${STATUS_BADGE[sub.status] ?? 'bg-secondary-subtle text-secondary'}`}>
                          {sub.status}
                        </span>
                      </td>
                      <td>{formatDate(sub.starts_at)}</td>
                      <td>{formatDate(sub.ends_at)}</td>
                      <td>{formatDate(sub.created_at)}</td>
                      <td>
                        <select
                          className="form-select form-select-sm"
                          style={{ width: '120px' }}
                          value={sub.status}
                          disabled={updating === sub.id}
                          onChange={e => handleStatusChange(sub, e.target.value)}
                        >
                          {STATUS_OPTIONS.filter(s => s).map(s => (
                            <option key={s} value={s}>{STATUS_LABELS[s]}</option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Footer count */}
          {!loading && (
            <div className="card-footer text-muted small">
              {visible.length} subscription{visible.length !== 1 ? 's' : ''}
              {search && ` matching "${search}"`}
            </div>
          )}

        </div>
      </div>
    </div>
  );
}
