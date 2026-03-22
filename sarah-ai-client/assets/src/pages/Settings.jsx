import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

export default function Settings() {
  const [form, setForm]     = useState({ widget_enabled: true, server_url: '', account_key: '', site_key: '', greeting_message: '' });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(false);
  const [saved, setSaved]     = useState(false);

  useEffect(() => {
    apiFetch('widget-settings')
      .then(res => {
        if (res.success) {
          setForm({
            widget_enabled:   res.data.widget_enabled,
            server_url:       res.data.server_url       || '',
            account_key:      res.data.account_key      || '',
            site_key:         res.data.site_key         || '',
            greeting_message: res.data.greeting_message || '',
          });
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  function handleToggle() {
    setForm(prev => ({ ...prev, widget_enabled: !prev.widget_enabled }));
  }

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    apiFetch('widget-settings', 'POST', form)
      .then(() => setSaved(true))
      .catch(() => {})
      .finally(() => setSaving(false));
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Settings</h1>
        <p className="text-muted small mb-0">Configure the Sarah AI chat widget.</p>
      </div>

      <form onSubmit={handleSave}>
        {/* Widget enable toggle */}
        <div className="card border-0 shadow-sm mb-3">
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
                  checked={form.widget_enabled}
                  onChange={handleToggle}
                  disabled={saving}
                  style={{ cursor: saving ? 'wait' : 'pointer', width: '2.5em', height: '1.25em' }}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Server connection */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white border-bottom">
            <h6 className="mb-0 fw-semibold">Server Connection</h6>
          </div>
          <div className="card-body">
            <p className="text-muted small mb-3">
              Enter the credentials provided by your Sarah AI platform administrator.
            </p>

            <div className="mb-3">
              <label className="form-label fw-semibold small">Server URL</label>
              <input
                type="url"
                className="form-control form-control-sm"
                name="server_url"
                value={form.server_url}
                onChange={handleChange}
                placeholder="https://your-server.example.com/wp-json/sarah-ai-server/v1"
                disabled={saving}
              />
              <div className="form-text">Base URL of the sarah-ai-server REST API.</div>
            </div>

            <div className="mb-3">
              <label className="form-label fw-semibold small">Account Key</label>
              <input
                type="text"
                className="form-control form-control-sm font-monospace"
                name="account_key"
                value={form.account_key}
                onChange={handleChange}
                placeholder="Paste your account key here"
                disabled={saving}
              />
            </div>

            <div className="mb-3">
              <label className="form-label fw-semibold small">Site Key</label>
              <input
                type="text"
                className="form-control form-control-sm font-monospace"
                name="site_key"
                value={form.site_key}
                onChange={handleChange}
                placeholder="Paste your site key here"
                disabled={saving}
              />
            </div>

            <div className="mb-0">
              <label className="form-label fw-semibold small">Greeting Message</label>
              <input
                type="text"
                className="form-control form-control-sm"
                name="greeting_message"
                value={form.greeting_message}
                onChange={handleChange}
                placeholder="Hi 👋 How can I help you today?"
                disabled={saving}
              />
              <div className="form-text">Shown instantly when the chat widget opens. Leave blank to skip.</div>
            </div>
          </div>
        </div>

        <div className="d-flex align-items-center gap-2">
          <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
            {saving ? 'Saving…' : 'Save Settings'}
          </button>
          {saved && <span className="text-success small">Saved.</span>}
        </div>
      </form>
    </>
  );
}
