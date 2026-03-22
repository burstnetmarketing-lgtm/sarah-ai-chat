import React, { useState, useEffect, useCallback } from 'react';
import { getUsage, getUsageSummary } from '../api/provisioning.js';

const DEFAULT_LIMIT = 50;

function SummaryCard({ label, value }) {
  return (
    <div className="col">
      <div className="card border-0 shadow-sm h-100">
        <div className="card-body text-center py-3">
          <div className="h4 fw-bold mb-1" style={{ color: '#1a3460' }}>{value.toLocaleString()}</div>
          <div className="text-muted small">{label}</div>
        </div>
      </div>
    </div>
  );
}

export default function Usage() {
  const today = new Date().toISOString().slice(0, 10);

  const [filters, setFilters] = useState({
    tenant_id:  '',
    site_id:    '',
    agent_id:   '',
    session_id: '',
    date_from:  '',
    date_to:    today,
  });

  const [summary, setSummary]   = useState(null);
  const [rows, setRows]         = useState([]);
  const [offset, setOffset]     = useState(0);
  const [hasMore, setHasMore]   = useState(false);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(null);

  const buildParams = useCallback((extraOffset = 0) => {
    const p = { limit: DEFAULT_LIMIT, offset: extraOffset };
    if (filters.tenant_id)  p.tenant_id  = filters.tenant_id;
    if (filters.site_id)    p.site_id    = filters.site_id;
    if (filters.agent_id)   p.agent_id   = filters.agent_id;
    if (filters.session_id) p.session_id = filters.session_id;
    if (filters.date_from)  p.date_from  = filters.date_from;
    if (filters.date_to)    p.date_to    = filters.date_to;
    return p;
  }, [filters]);

  const load = useCallback((resetOffset = true) => {
    const newOffset = resetOffset ? 0 : offset;
    setLoading(true);
    setError(null);

    const params = buildParams(newOffset);
    const summaryParams = { ...params };
    delete summaryParams.limit;
    delete summaryParams.offset;

    Promise.all([
      getUsage(params),
      resetOffset ? getUsageSummary(summaryParams) : Promise.resolve(null),
    ])
      .then(([usageRes, summaryRes]) => {
        if (!usageRes.success) throw new Error('Failed to load usage data.');
        setRows(usageRes.data);
        setHasMore(usageRes.data.length === DEFAULT_LIMIT);
        if (summaryRes?.success) setSummary(summaryRes.data);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));

    if (resetOffset) setOffset(0);
  }, [buildParams, offset]);

  useEffect(() => { load(true); }, []); // initial load only

  function handleFilter(e) {
    setFilters(prev => ({ ...prev, [e.target.name]: e.target.value }));
  }

  function handleApply(e) {
    e.preventDefault();
    load(true);
  }

  function handleReset() {
    setFilters({ tenant_id: '', site_id: '', agent_id: '', session_id: '', date_from: '', date_to: today });
  }

  function handlePrev() {
    const newOffset = Math.max(0, offset - DEFAULT_LIMIT);
    setOffset(newOffset);
    load(false);
  }

  function handleNext() {
    const newOffset = offset + DEFAULT_LIMIT;
    setOffset(newOffset);
    load(false);
  }

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Usage</h1>
        <p className="text-muted small mb-0">Runtime usage records — requests, tokens, sessions.</p>
      </div>

      {/* Summary */}
      {summary && (
        <div className="row row-cols-3 g-3 mb-4">
          <SummaryCard label="Total Requests"   value={summary.total_requests} />
          <SummaryCard label="Tokens In"        value={summary.total_tokens_in} />
          <SummaryCard label="Tokens Out"       value={summary.total_tokens_out} />
        </div>
      )}

      {/* Filters */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body">
          <form onSubmit={handleApply}>
            <div className="row g-2 align-items-end">
              <div className="col-auto">
                <label className="form-label small mb-1">Tenant ID</label>
                <input name="tenant_id" value={filters.tenant_id} onChange={handleFilter}
                  className="form-control form-control-sm" placeholder="All" style={{ width: 90 }} />
              </div>
              <div className="col-auto">
                <label className="form-label small mb-1">Site ID</label>
                <input name="site_id" value={filters.site_id} onChange={handleFilter}
                  className="form-control form-control-sm" placeholder="All" style={{ width: 90 }} />
              </div>
              <div className="col-auto">
                <label className="form-label small mb-1">Agent ID</label>
                <input name="agent_id" value={filters.agent_id} onChange={handleFilter}
                  className="form-control form-control-sm" placeholder="All" style={{ width: 90 }} />
              </div>
              <div className="col-auto">
                <label className="form-label small mb-1">Session ID</label>
                <input name="session_id" value={filters.session_id} onChange={handleFilter}
                  className="form-control form-control-sm" placeholder="All" style={{ width: 100 }} />
              </div>
              <div className="col-auto">
                <label className="form-label small mb-1">From</label>
                <input type="date" name="date_from" value={filters.date_from} onChange={handleFilter}
                  className="form-control form-control-sm" />
              </div>
              <div className="col-auto">
                <label className="form-label small mb-1">To</label>
                <input type="date" name="date_to" value={filters.date_to} onChange={handleFilter}
                  className="form-control form-control-sm" />
              </div>
              <div className="col-auto d-flex gap-2">
                <button type="submit" className="btn btn-sm btn-primary">Apply</button>
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={handleReset}>Reset</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {/* Table */}
      <div className="card border-0 shadow-sm">
        <div className="card-body p-0">
          {error && <p className="text-danger small p-3 mb-0">{error}</p>}
          {loading && <p className="text-muted small p-3 mb-0">Loading…</p>}
          {!loading && !error && rows.length === 0 && (
            <p className="text-muted small p-3 mb-0">No usage records found.</p>
          )}
          {!loading && !error && rows.length > 0 && (
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Tenant</th>
                    <th>Site</th>
                    <th>Agent</th>
                    <th>Session</th>
                    <th>Tokens In</th>
                    <th>Tokens Out</th>
                    <th>Model</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map(row => (
                    <tr key={row.id}>
                      <td className="text-muted">{row.id}</td>
                      <td><span className="badge bg-secondary">{row.event_type}</span></td>
                      <td>{row.tenant_id ?? '—'}</td>
                      <td>{row.site_id ?? '—'}</td>
                      <td>{row.agent_id ?? '—'}</td>
                      <td>{row.session_id ?? '—'}</td>
                      <td>{row.tokens_in  ?? <span className="text-muted">—</span>}</td>
                      <td>{row.tokens_out ?? <span className="text-muted">—</span>}</td>
                      <td className="text-muted">{row.meta?.model ?? '—'}</td>
                      <td className="text-muted text-nowrap">{row.created_at}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Pagination */}
        {!loading && !error && (offset > 0 || hasMore) && (
          <div className="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2">
            <button className="btn btn-sm btn-outline-secondary" onClick={handlePrev} disabled={offset === 0}>
              &larr; Prev
            </button>
            <span className="text-muted small">Showing {offset + 1}–{offset + rows.length}</span>
            <button className="btn btn-sm btn-outline-secondary" onClick={handleNext} disabled={!hasMore}>
              Next &rarr;
            </button>
          </div>
        )}
      </div>
    </>
  );
}
