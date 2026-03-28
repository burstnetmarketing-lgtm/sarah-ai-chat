import { StatusBadge } from './helpers.jsx';

export default function TenantInfoPanel({ tenant }) {
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
