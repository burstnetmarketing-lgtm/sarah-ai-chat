import React, { useState, useEffect } from 'react';
import { listTenants } from '../api/provisioning.js';

const TENANT_STATUS_BADGE = {
  active:    'bg-success-subtle text-success',
  inactive:  'bg-secondary-subtle text-secondary',
  suspended: 'bg-warning-subtle text-warning',
  archived:  'bg-dark-subtle text-dark',
};

export default function Tenants({ onNavigate }) {
  const [tenants, setTenants] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch]   = useState('');

  function load() {
    setLoading(true);
    listTenants()
      .then(res => { if (res.success) setTenants(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, []);

  const visible = tenants.filter(row => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (row.tenant.name ?? '').toLowerCase().includes(q) ||
           (row.tenant.slug ?? '').toLowerCase().includes(q);
  });

  return (
    <div className="row">
      <div className="col-12">

        <div className="card">
          <div className="d-flex justify-content-between flex-wrap align-items-center card-header">
            <div>
              <h4 className="card-title">Tenants</h4>
              <p className="text-muted fw-semibold mb-0">All registered tenants.</p>
            </div>
            <div className="d-flex align-items-center gap-2">
              <button
                className="btn btn-primary btn-sm orange-bg"
                onClick={() => onNavigate('create-tenant')}
              >
                + New Tenant
              </button>
              <input
                type="text"
                className="form-control form-control-sm"
                style={{ width: '180px' }}
                placeholder="Search name or slug…"
                value={search}
                onChange={e => setSearch(e.target.value)}
              />
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>
                ↺
              </button>
            </div>
          </div>

          <div className="p-0 card-body">
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead>
                  <tr className="table-light text-capitalize">
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {loading ? (
                    <tr><td colSpan={5} className="text-muted py-4 text-center">Loading…</td></tr>
                  ) : visible.length === 0 ? (
                    <tr><td colSpan={5} className="text-muted py-4 text-center">No tenants found.</td></tr>
                  ) : visible.map(row => (
                    <tr key={row.tenant.id}>
                      <td>
                        <button
                          className="btn btn-link p-0 text-start fw-semibold"
                          onClick={() => onNavigate('tenant-detail', row.tenant.uuid)}
                        >
                          {row.tenant.name} <span className="text-muted fw-normal small">(#{row.tenant.id})</span>
                        </button>
                      </td>
                      <td className="text-muted">{row.tenant.slug}</td>
                      <td>
                        <span className={`badge ${TENANT_STATUS_BADGE[row.tenant.status] ?? 'bg-secondary-subtle text-secondary'}`}>
                          {row.tenant.status ?? '—'}
                        </span>
                      </td>
                      <td className="text-muted small">
                        {row.tenant.created_at ? row.tenant.created_at.slice(0, 10) : '—'}
                      </td>
                      <td>
                        {row.tenant.setup_complete == 1 ? (
                          <button
                            className="btn btn-outline-secondary btn-sm"
                            onClick={() => onNavigate('tenant-detail', row.tenant.uuid)}
                          >
                            Edit →
                          </button>
                        ) : (
                          <button
                            className="btn btn-outline-primary btn-sm"
                            onClick={() => onNavigate('tenant-detail', row.tenant.uuid)}
                          >
                            Manage →
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {!loading && (
            <div className="card-footer text-muted small">
              {visible.length} tenant{visible.length !== 1 ? 's' : ''}
              {search && ` matching "${search}"`}
            </div>
          )}
        </div>

      </div>
    </div>
  );
}
