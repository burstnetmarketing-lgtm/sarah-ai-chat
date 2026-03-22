import React, { useState, useEffect } from 'react';
import { listTenants, createTenant } from '../api/provisioning.js';

function StatusBadge({ status }) {
  const map = { active: 'success', inactive: 'secondary', suspended: 'warning', archived: 'dark' };
  return <span className={`badge bg-${map[status] ?? 'secondary'}`}>{status ?? '—'}</span>;
}

function SubscriptionBadge({ status }) {
  const map = { trialing: 'info', active: 'success', expired: 'danger', cancelled: 'secondary' };
  return <span className={`badge bg-${map[status] ?? 'secondary'}`}>{status ?? 'none'}</span>;
}

export default function Tenants({ onNavigate }) {
  const [tenants, setTenants]   = useState([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(null);
  const [saving, setSaving]     = useState(false);
  const [form, setForm]         = useState({ name: '', slug: '' });
  const [formError, setFormError] = useState(null);

  function load() {
    setLoading(true);
    listTenants()
      .then(res => { if (res.success) setTenants(res.data); })
      .catch(() => setError('Failed to load tenants.'))
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
      if (res.success) {
        setForm({ name: '', slug: '' });
        load();
      } else {
        setFormError(res.message ?? 'Failed to create tenant.');
      }
    } catch {
      setFormError('Request failed.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Tenants</h1>
        <p className="text-muted small mb-0">Create and manage tenants. Click Setup to configure a tenant environment.</p>
      </div>

      {/* Create Form */}
      <div className="card border-0 shadow-sm mb-4">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Create New Tenant</h6>
        </div>
        <div className="card-body">
          <form onSubmit={handleCreate}>
            <div className="row g-3 align-items-end">
              <div className="col-md-5">
                <label className="form-label fw-semibold small">Name *</label>
                <input className="form-control form-control-sm" placeholder="Acme Corp"
                  value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
              </div>
              <div className="col-md-4">
                <label className="form-label fw-semibold small">Slug (optional)</label>
                <input className="form-control form-control-sm" placeholder="acme-corp"
                  value={form.slug} onChange={e => setForm(f => ({ ...f, slug: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <button className="btn btn-primary btn-sm w-100" type="submit" disabled={saving}>
                  {saving ? 'Creating…' : '+ Create Tenant'}
                </button>
              </div>
            </div>
            {formError && <div className="alert alert-danger py-1 mt-2 small">{formError}</div>}
            <p className="text-muted small mt-2 mb-0">A trial subscription will be created automatically.</p>
          </form>
        </div>
      </div>

      {/* Tenant List */}
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
          <h6 className="mb-0 fw-semibold">All Tenants</h6>
          <button className="btn btn-outline-secondary btn-sm" onClick={load} disabled={loading}>Refresh</button>
        </div>
        <div className="card-body p-0">
          {loading ? (
            <p className="p-3 text-muted small mb-0">Loading…</p>
          ) : error ? (
            <p className="p-3 text-danger small mb-0">{error}</p>
          ) : tenants.length === 0 ? (
            <p className="p-3 text-muted small mb-0">No tenants yet.</p>
          ) : (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Slug</th>
                  <th>Status</th>
                  <th>Subscription</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {tenants.map(row => (
                  <tr key={row.tenant.id}>
                    <td className="text-muted small">{row.tenant.id}</td>
                    <td className="fw-semibold">{row.tenant.name}</td>
                    <td className="text-muted small">{row.tenant.slug}</td>
                    <td><StatusBadge status={row.tenant.status} /></td>
                    <td><SubscriptionBadge status={row.subscription_status} /></td>
                    <td>
                      <button className="btn btn-sm btn-outline-primary"
                        onClick={() => onNavigate('tenant-detail', row.tenant.uuid)}>
                        Setup →
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  );
}
