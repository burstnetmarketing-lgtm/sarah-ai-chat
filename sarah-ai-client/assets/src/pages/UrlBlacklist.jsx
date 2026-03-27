import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

export default function UrlBlacklist() {
  const [patterns, setPatterns] = useState([]);
  const [loading, setLoading]   = useState(true);
  const [newPattern, setNew]    = useState('');
  const [saving, setSaving]     = useState(false);

  useEffect(() => {
    apiFetch('widget-settings')
      .then(res => {
        if (res.success) {
          const raw = res.data.widget_blacklist || '';
          setPatterns(raw.split('\n').map(s => s.trim()).filter(Boolean));
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  function save(list) {
    setSaving(true);
    apiFetch('widget-settings', 'POST', { widget_blacklist: list.join('\n') })
      .catch(() => {})
      .finally(() => setSaving(false));
  }

  function handleAdd(e) {
    e.preventDefault();
    const p = newPattern.trim();
    if (!p || patterns.includes(p)) return;
    const next = [...patterns, p];
    setPatterns(next);
    setNew('');
    save(next);
  }

  function handleDelete(p) {
    const next = patterns.filter(x => x !== p);
    setPatterns(next);
    save(next);
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">URL Blacklist</h1>
        <p className="text-muted small mb-0">
          The chat widget will <strong>not</strong> appear on pages matching these URL patterns.
          Use <code>*</code> as a wildcard (e.g. <code>/account/*</code>, <code>/dashboard*</code>).
        </p>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Add Pattern</h6>
        </div>
        <div className="card-body">
          <form onSubmit={handleAdd} className="d-flex gap-2">
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder="e.g. /my-account* or /dashboard/*"
              value={newPattern}
              onChange={e => setNew(e.target.value)}
              disabled={saving}
            />
            <button
              type="submit"
              className="btn btn-primary btn-sm text-nowrap"
              disabled={saving || !newPattern.trim()}
            >
              Add
            </button>
          </form>
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Blocked Patterns ({patterns.length})</h6>
        </div>
        {patterns.length === 0 ? (
          <div className="card-body text-muted small">
            No patterns yet — the widget appears on all pages.
          </div>
        ) : (
          <ul className="list-group list-group-flush">
            {patterns.map(p => (
              <li key={p} className="list-group-item d-flex align-items-center gap-2 py-2">
                <code className="flex-grow-1 small">{p}</code>
                <button
                  className="btn btn-outline-danger btn-sm"
                  onClick={() => handleDelete(p)}
                  disabled={saving}
                >
                  Remove
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  );
}
