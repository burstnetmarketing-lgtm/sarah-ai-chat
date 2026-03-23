import React, { useState, useEffect } from 'react';
import { listTenants, createTenant } from '../api/provisioning.js';

const TENANT_STATUS_BADGE = {
  active:    'bg-success-subtle text-success',
  inactive:  'bg-secondary-subtle text-secondary',
  suspended: 'bg-warning-subtle text-warning',
  archived:  'bg-dark-subtle text-dark',
};


export default function Tenants({ onNavigate }) {
  const [tenants, setTenants]     = useState([]);
  const [loading, setLoading]     = useState(true);
  const [saving, setSaving]       = useState(false);
  const [form, setForm]           = useState({ name: '', slug: '' });
  const [formError, setFormError] = useState(null);
  const [search, setSearch]       = useState('');

  function load() {
    setLoading(true);
    listTenants()
      .then(res => { if (res.success) setTenants(res.data); })
      .finally(() => setLoading(false));
  }

  useEffect(() => { load(); }, []);

  async function handleCreate(e) {
    e.preventDefault();
    if (!form.name.trim()) return;
    setSaving(true);
    setFormError(null);
    try {
      const res = await createTenant({ name: form.name.trim(), slug: form.slug.trim() || undefined });
      if (res.success) { setForm({ name: '', slug: '' }); load(); }
      else setFormError(res.message ?? 'Failed to create tenant.');
    } catch {
      setFormError('Request failed.');
    } finally {
      setSaving(false);
    }
  }

  const visible = tenants.filter(row => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (row.tenant.name ?? '').toLowerCase().includes(q) ||
           (row.tenant.slug ?? '').toLowerCase().includes(q);
  });

  return (
    <div className="row">
      <div className="col-12">

        {/* Create New Tenant */}
        <div className="card mb-4">
          <div className="card-header">
            <h4 className="card-title">Create New Tenant</h4>
          </div>
          <div className="card-body">
            <form onSubmit={handleCreate}>
              <div className="row g-2 align-items-end">
                <div className="col-md-5 form-group">
                  <label className="form-label">Name *</label>
                  <input className="form-control" placeholder="Acme Corp"
                    value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
                </div>
                <div className="col-md-4 form-group">
                  <label className="form-label">Slug <span className="text-muted">(optional)</span></label>
                  <input className="form-control" placeholder="acme-corp"
                    value={form.slug} onChange={e => setForm(f => ({ ...f, slug: e.target.value }))} />
                </div>
                <div className="col-md-3">
                  <button className="btn btn-primary w-100" type="submit" disabled={saving}>
                    {saving ? 'Creating…' : '+ Create Tenant'}
                  </button>
                </div>
              </div>
              {formError && <div className="alert alert-danger py-2 mt-2 mb-0">{formError}</div>}
            </form>
          </div>
        </div>

        {/* Tenant List */}
        <div className="card">
          <div className="d-flex justify-content-between flex-wrap align-items-center card-header">
            <div>
              <h4 className="card-title">Tenants</h4>
              <p className="text-muted fw-semibold mb-0">All registered tenants.</p>
            </div>
            <div className="d-flex align-items-center gap-2">
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
                          {row.tenant.name}
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
                            Setup →
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
