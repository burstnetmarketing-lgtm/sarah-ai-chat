import { useState } from 'react';

const BASE = '/wp-json/sarah-ai-server/v1';

const METHOD_COLORS = {
  GET:    'text-bg-success',
  POST:   'text-bg-primary',
  DELETE: 'text-bg-danger',
  PUT:    'text-bg-warning',
};

const BADGE_LABEL = {
  'bg-success': 'public',
  'bg-primary': 'admin',
  'bg-info':    'client',
  'bg-warning': 'setup',
};

function CopyButton({ text }) {
  const [copied, setCopied] = useState(false);
  function copy() {
    navigator.clipboard?.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  }
  return (
    <button className="btn btn-sm btn-outline-secondary py-0 px-1 ms-2" style={{ fontSize: '0.65rem' }} onClick={copy}>
      {copied ? '✓' : 'copy'}
    </button>
  );
}

function EndpointRow({ ep }) {
  const [open, setOpen] = useState(false);
  const fullPath = BASE + ep.path;

  return (
    <div className="border rounded mb-2">
      <div
        className="d-flex align-items-center gap-2 px-3 py-2"
        style={{ cursor: 'pointer', background: open ? '#f8f9fa' : 'white' }}
        onClick={() => setOpen(o => !o)}
      >
        <span className={`badge ${METHOD_COLORS[ep.method] ?? 'text-bg-secondary'}`} style={{ minWidth: '52px', textAlign: 'center' }}>
          {ep.method}
        </span>
        <code className="text-dark small flex-grow-1">{ep.path}</code>
        <span className="text-muted small d-none d-md-inline">{ep.summary}</span>
        <span className="text-muted" style={{ fontSize: '0.7rem' }}>{open ? '▲' : '▼'}</span>
      </div>

      {open && (
        <div className="border-top px-3 py-3 bg-white">
          <p className="mb-2 text-muted small">{ep.summary}</p>
          {ep.note && <div className="alert alert-warning py-1 px-2 small mb-2">{ep.note}</div>}
          <div className="mb-2">
            <span className="text-muted small fw-semibold">Auth: </span>
            <span className="badge bg-light text-dark border small">{ep.auth}</span>
          </div>
          <div className="mb-2 d-flex align-items-center">
            <span className="text-muted small fw-semibold me-1">Endpoint:</span>
            <code className="small">{fullPath}</code>
            <CopyButton text={fullPath} />
          </div>
          {ep.params.length > 0 && (
            <div className="mb-2">
              <div className="text-muted small fw-semibold mb-1">Parameters:</div>
              <table className="table table-sm table-bordered mb-0" style={{ fontSize: '0.78rem' }}>
                <thead className="table-light">
                  <tr><th>Name</th><th>Type</th><th>Required</th><th>Description</th></tr>
                </thead>
                <tbody>
                  {ep.params.map((p, i) => (
                    <tr key={i}>
                      <td><code>{p.name}</code></td>
                      <td className="text-muted">{p.type}</td>
                      <td>{p.req ? <span className="text-danger fw-semibold">✓</span> : <span className="text-muted">—</span>}</td>
                      <td className="text-muted">{p.desc}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          <div>
            <span className="text-muted small fw-semibold">Response: </span>
            <code className="small text-success">{ep.response}</code>
          </div>
        </div>
      )}
    </div>
  );
}

export function GroupPanel({ group }) {
  return (
    <div className="mb-4">
      <div className="d-flex align-items-center gap-2 mb-1">
        <h6 className="mb-0 fw-semibold">{group.label}</h6>
        <span className={`badge ${group.badge}`} style={{ fontSize: '0.65rem' }}>
          {BADGE_LABEL[group.badge] ?? 'other'}
        </span>
        <span className="text-muted small">({group.endpoints.length} endpoints)</span>
      </div>
      <p className="text-muted small mb-2">{group.description}</p>
      {group.endpoints.map((ep, i) => <EndpointRow key={i} ep={ep} />)}
    </div>
  );
}
