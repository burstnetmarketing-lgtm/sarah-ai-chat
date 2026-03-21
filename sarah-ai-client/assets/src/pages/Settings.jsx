import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

export default function Settings() {
  const [enabled, setEnabled] = useState(true);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    apiFetch('widget-settings')
      .then(res => { if (res.success) setEnabled(res.data.widget_enabled); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  function handleToggle() {
    const next = !enabled;
    setEnabled(next);
    setSaving(true);
    apiFetch('widget-settings', 'POST', { widget_enabled: next })
      .catch(() => setEnabled(!next))
      .finally(() => setSaving(false));
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Settings</h1>
        <p className="text-muted small mb-0">Configure the Sarah AI chat widget.</p>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Chat Widget</h6>
        </div>
        <div className="card-body">
          <div className="d-flex align-items-center justify-content-between">
            <div>
              <div className="fw-semibold small">Enable Chat Widget</div>
              <div className="text-muted small">Show the floating chat button on the website frontend.</div>
            </div>
            <div className="form-check form-switch mb-0">
              <input
                className="form-check-input"
                type="checkbox"
                role="switch"
                checked={enabled}
                onChange={handleToggle}
                disabled={saving}
                style={{ cursor: saving ? 'wait' : 'pointer', width: '2.5em', height: '1.25em' }}
              />
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
