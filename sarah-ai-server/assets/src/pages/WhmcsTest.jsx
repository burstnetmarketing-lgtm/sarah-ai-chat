import React, { useState } from 'react';
import { apiFetch } from '../api/client.js';

export default function WhmcsTest() {
  const [licenseKey, setLicenseKey] = useState('');
  const [loading,    setLoading]    = useState(false);
  const [result,     setResult]     = useState(null);
  const [error,      setError]      = useState(null);

  async function handleTest(e) {
    e.preventDefault();
    if (!licenseKey.trim()) return;

    setLoading(true);
    setResult(null);
    setError(null);

    try {
      const res = await apiFetch('whmcs-test', 'POST', { license_key: licenseKey.trim() });
      if (!res.success) throw new Error(res.message || 'Request failed.');
      setResult(res.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }

  const status     = result?.result?.status ?? '';
  const isActive   = result?.is_active;
  const statusLower = status.toLowerCase();

  const statusBadge = isActive
    ? 'bg-success'
    : statusLower === ''
      ? 'bg-secondary'
      : 'bg-danger';

  const SKIP_KEYS = ['addons_parsed', 'configoptions_parsed'];
  const resultRows = result?.result
    ? Object.entries(result.result).filter(([k]) => !SKIP_KEYS.includes(k) && k !== 'status')
    : [];

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">WHMCS License Test</h1>
        <p className="text-muted small mb-0">
          Test a WHMCS license key directly against the licensing server — bypasses cache.
        </p>
      </div>

      {/* Config info */}
      {result && (
        <div className="alert alert-light border small py-2 mb-3">
          <span className="fw-semibold">Endpoint: </span>
          {result.whmcs_api_url
            ? <code>{result.whmcs_api_url}/modules/servers/licensing/verify.php</code>
            : <span className="text-warning">whmcs_api_url not set — grace mode (all keys valid)</span>
          }
        </div>
      )}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Test a License Key</h6>
        </div>
        <div className="card-body">
          {error && <div className="alert alert-danger small py-2">{error}</div>}

          <form onSubmit={handleTest}>
            <div className="mb-3">
              <label className="form-label small fw-semibold">WHMCS License Key</label>
              <input
                type="text"
                className="form-control form-control-sm font-monospace"
                value={licenseKey}
                onChange={e => setLicenseKey(e.target.value)}
                placeholder="Enter license key to test…"
                disabled={loading}
                autoComplete="off"
              />
            </div>
            <button type="submit" className="btn btn-sm btn-primary" disabled={loading || !licenseKey.trim()}>
              {loading ? <><span className="spinner-border spinner-border-sm me-2" />Testing…</> : 'Test Key'}
            </button>
          </form>
        </div>
      </div>

      {/* Result */}
      {result && (
        <div className="card border-0 shadow-sm">
          <div className="card-header bg-white border-bottom d-flex align-items-center gap-2">
            <h6 className="mb-0 fw-semibold">Result</h6>
            <span className={`badge ${statusBadge}`}>{status || '—'}</span>
            {isActive && <span className="text-success small">✓ Valid</span>}
            {!isActive && status && <span className="text-danger small">✗ Not active</span>}
          </div>
          <div className="card-body p-0">
            {resultRows.length > 0 ? (
              <table className="table table-sm table-bordered mb-0" style={{ fontSize: '0.8rem' }}>
                <tbody>
                  {resultRows.map(([key, val]) => (
                    <tr key={key}>
                      <td className="fw-semibold text-muted" style={{ width: '30%' }}>{key}</td>
                      <td className="font-monospace" style={{ wordBreak: 'break-all' }}>
                        {val === '' || val === null || val === undefined
                          ? <span className="text-muted">—</span>
                          : String(val)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <p className="text-muted small p-3 mb-0">No additional fields returned.</p>
            )}
          </div>
        </div>
      )}
    </>
  );
}
