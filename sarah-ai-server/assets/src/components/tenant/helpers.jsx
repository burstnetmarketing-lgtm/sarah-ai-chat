// Shared helper components used across tenant section panels.

export function StatusBadge({ status, map }) {
  const colors = map ?? { active: 'success', inactive: 'secondary', suspended: 'warning', trialing: 'info', archived: 'dark' };
  return <span className={`badge bg-${colors[status] ?? 'secondary'}`}>{status ?? '—'}</span>;
}

export function SectionHeader({ title, onRefresh, refreshing }) {
  return (
    <div className="d-flex justify-content-between align-items-center mb-2">
      <h6 className="fw-semibold mb-0">{title}</h6>
      {onRefresh && (
        <button className="btn btn-outline-secondary btn-sm" onClick={onRefresh} disabled={refreshing}>↺</button>
      )}
    </div>
  );
}

export function Alert({ type, msg }) {
  if (!msg) return null;
  return <div className={`alert alert-${type} py-1 px-2 small mt-2 mb-0`}>{msg}</div>;
}

export function PrereqCard({ msg }) {
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body text-center py-5 text-muted small">{msg}</div>
    </div>
  );
}
