import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

const PROVIDERS = [
  { value: 'openai', label: 'OpenAI' },
];

async function serverFetch(serverUrl, platformKey, path, method = 'GET', body = null) {
  const headers = { 'Content-Type': 'application/json', 'X-Sarah-Platform-Key': platformKey };
  const res = await fetch(`${serverUrl}/${path}`, {
    method,
    headers,
    body: body !== null ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) throw new Error(`Server error: ${res.status}`);
  return res.json();
}

export default function Settings() {
  const [form, setForm]     = useState({ widget_enabled: true, server_url: '', account_key: '', site_key: '', platform_key: '', greeting_message: '' });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(false);
  const [saved, setSaved]     = useState(false);

  // API key section state
  const [apiKeyProvider, setApiKeyProvider] = useState('openai');
  const [apiKeyValue, setApiKeyValue]       = useState('');
  const [savedProviders, setSavedProviders] = useState([]);
  const [apiKeySaving, setApiKeySaving]     = useState(false);
  const [apiKeySaved, setApiKeySaved]       = useState(false);
  const [apiKeyError, setApiKeyError]       = useState('');

  useEffect(() => {
    apiFetch('widget-settings')
      .then(res => {
        if (res.success) {
          const d = res.data;
          setForm({
            widget_enabled:   d.widget_enabled,
            server_url:       d.server_url       || '',
            account_key:      d.account_key      || '',
            site_key:         d.site_key         || '',
            platform_key:     d.platform_key     || '',
            greeting_message: d.greeting_message || '',
          });
          // Load saved API key providers from server if connection is configured
          if (d.server_url && d.account_key && d.site_key && d.platform_key) {
            serverFetch(d.server_url, d.platform_key, `client/api-keys?account_key=${encodeURIComponent(d.account_key)}&site_key=${encodeURIComponent(d.site_key)}`)
              .then(r => { if (r.success) setSavedProviders(r.data.providers || []); })
              .catch(() => {});
          }
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

  function handleApiKeySave(e) {
    e.preventDefault();
    if (!form.server_url || !form.account_key || !form.site_key || !form.platform_key) {
      setApiKeyError('Connection not configured. Please run the Quick Setup first.');
      return;
    }
    setApiKeySaving(true);
    setApiKeySaved(false);
    setApiKeyError('');
    serverFetch(form.server_url, form.platform_key, 'client/api-key', 'POST', {
      account_key: form.account_key,
      site_key:    form.site_key,
      provider:    apiKeyProvider,
      api_key:     apiKeyValue,
    })
      .then(r => {
        if (r.success) {
          setSavedProviders(r.data.providers || []);
          setApiKeyValue('');
          setApiKeySaved(true);
        }
      })
      .catch(() => setApiKeyError('Failed to save key. Check server connection.'))
      .finally(() => setApiKeySaving(false));
  }

  function handleApiKeyClear(provider) {
    if (!form.server_url || !form.account_key || !form.site_key || !form.platform_key) return;
    serverFetch(form.server_url, form.platform_key, 'client/api-key', 'POST', {
      account_key: form.account_key,
      site_key:    form.site_key,
      provider,
      api_key:     '',
    })
      .then(r => { if (r.success) setSavedProviders(r.data.providers || []); })
      .catch(() => {});
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    apiFetch('widget-settings', 'POST', { widget_enabled: form.widget_enabled, greeting_message: form.greeting_message })
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

        {/* Greeting message */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white border-bottom">
            <h6 className="mb-0 fw-semibold">Chat Behaviour</h6>
          </div>
          <div className="card-body">
            <div className="mb-3">
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
            <div className="d-flex align-items-center justify-content-end gap-2">
              {saved && <span className="text-success small">Saved.</span>}
              <button type="submit" className="btn btn-sm btn-primary orange-bg" disabled={saving}>
                {saving ? 'Saving…' : 'Save Settings'}
              </button>
            </div>
          </div>
        </div>
      </form>

      {/* AI Provider API Keys */}
      <form onSubmit={handleApiKeySave} className="mt-3">
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white border-bottom">
            <h6 className="mb-0 fw-semibold">AI Provider Keys</h6>
          </div>
          <div className="card-body">
            <p className="text-muted small mb-3">
              Enter your own API key to use your account's quota. If not set, the platform's shared key is used.
            </p>

            {savedProviders.length > 0 && (
              <div className="mb-3">
                <div className="text-muted small fw-semibold mb-1">Keys saved on server:</div>
                <div className="d-flex flex-wrap gap-2">
                  {savedProviders.map(p => (
                    <span key={p} className="badge bg-success-subtle text-success border border-success-subtle d-flex align-items-center gap-1">
                      {p}
                      <button
                        type="button"
                        className="btn-close btn-close-sm"
                        style={{ fontSize: '0.6rem' }}
                        onClick={() => handleApiKeyClear(p)}
                        title="Remove key"
                      />
                    </span>
                  ))}
                </div>
              </div>
            )}

            <div className="row g-2 align-items-end">
              <div className="col-auto">
                <label className="form-label fw-semibold small">Provider</label>
                <select
                  className="form-select form-select-sm"
                  value={apiKeyProvider}
                  onChange={e => setApiKeyProvider(e.target.value)}
                  disabled={apiKeySaving}
                >
                  {PROVIDERS.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                </select>
              </div>
              <div className="col">
                <label className="form-label fw-semibold small">API Key</label>
                <input
                  type="password"
                  className="form-control form-control-sm font-monospace"
                  value={apiKeyValue}
                  onChange={e => setApiKeyValue(e.target.value)}
                  placeholder="Paste your API key here"
                  disabled={apiKeySaving}
                  autoComplete="new-password"
                />
              </div>
              <div className="col-auto">
                <button type="submit" className="btn btn-sm btn-primary orange-bg" disabled={apiKeySaving}>
                  {apiKeySaving ? 'Saving…' : 'Save Key'}
                </button>
              </div>
            </div>

            {apiKeySaved && <div className="text-success small mt-2">Key saved.</div>}
            {apiKeyError && <div className="text-danger small mt-2">{apiKeyError}</div>}
          </div>
        </div>
      </form>
    </>
  );
}
