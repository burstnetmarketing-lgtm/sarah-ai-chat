import React, { useState, useEffect } from 'react';
import { getPlatformSettings, updatePlatformSettings } from '../api/provisioning.js';

const FIELDS = [
  {
    key:         'platform_name',
    label:       'Platform Name',
    type:        'text',
    placeholder: 'Sarah',
    help:        'Display name for this platform.',
  },
  {
    key:         'openai_api_key',
    label:       'OpenAI API Key',
    type:        'password',
    placeholder: 'sk-...',
    help:        'Required for real AI responses. Leave blank to keep current value. Stored securely.',
    sensitive:   true,
  },
  {
    key:         'platform_api_key',
    label:       'Platform API Key',
    type:        'text',
    placeholder: 'www.BurstNET.com.au',
    help:        'Static key required on the X-Sarah-Platform-Key header for session inspection endpoints.',
  },
  {
    key:         'trial_duration_days',
    label:       'Trial Duration (days)',
    type:        'number',
    placeholder: '14',
    help:        'Number of days for new tenant trial subscriptions.',
  },
  {
    key:         'default_agent_slug',
    label:       'Default Agent Slug',
    type:        'text',
    placeholder: 'gpt-4o-mini',
    help:        'Slug of the agent assigned by default to new sites.',
  },
];

export default function Settings() {
  const [form,    setForm]    = useState({});
  const [keySet,  setKeySet]  = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [saved,   setSaved]   = useState(false);
  const [error,   setError]   = useState(null);

  useEffect(() => {
    getPlatformSettings()
      .then(res => {
        if (!res.success) throw new Error('Failed to load settings.');
        setForm(res.data);
        setKeySet(res.data.openai_api_key_set);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  function handleChange(e) {
    const { name, value, type, checked } = e.target;
    setForm(prev => ({ ...prev, [name]: type === 'checkbox' ? (checked ? '1' : '0') : value }));
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    setError(null);

    // Don't send masked placeholder for openai_api_key if unchanged
    const payload = { ...form };
    if (payload.openai_api_key && payload.openai_api_key.includes('•')) {
      delete payload.openai_api_key;
    }
    delete payload.openai_api_key_set;

    updatePlatformSettings(payload)
      .then(res => {
        if (!res.success) throw new Error('Save failed.');
        setSaved(true);
        // Reload to show updated masked key
        return getPlatformSettings();
      })
      .then(res => {
        if (res?.success) {
          setForm(res.data);
          setKeySet(res.data.openai_api_key_set);
        }
      })
      .catch(err => setError(err.message))
      .finally(() => setSaving(false));
  }

  if (loading) return <p className="text-muted small p-3">Loading…</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Settings</h1>
        <p className="text-muted small mb-0">Platform configuration — API keys, defaults, and behaviour.</p>
      </div>

      {error && <div className="alert alert-danger small py-2">{error}</div>}

      <form onSubmit={handleSave}>
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white border-bottom">
            <h6 className="mb-0 fw-semibold">Platform</h6>
          </div>
          <div className="card-body">
            {FIELDS.map(field => (
              <div className="mb-3" key={field.key}>
                <label className="form-label small fw-semibold">
                  {field.label}
                  {field.key === 'openai_api_key' && (
                    <span className={`ms-2 badge ${keySet ? 'bg-success' : 'bg-warning text-dark'}`}>
                      {keySet ? 'Set' : 'Not set — using mock responses'}
                    </span>
                  )}
                </label>
                <input
                  type={field.type}
                  name={field.key}
                  className={`form-control form-control-sm${field.sensitive ? ' font-monospace' : ''}`}
                  value={form[field.key] ?? ''}
                  onChange={handleChange}
                  placeholder={field.placeholder}
                  disabled={saving}
                  autoComplete={field.type === 'password' ? 'new-password' : 'off'}
                />
                {field.help && <div className="form-text">{field.help}</div>}
              </div>
            ))}

            <div className="mb-3">
              <label className="form-label small fw-semibold">Logging</label>
              <div className="form-check form-switch">
                <input
                  className="form-check-input"
                  type="checkbox"
                  name="logging_enabled"
                  checked={form.logging_enabled === '1'}
                  onChange={handleChange}
                  disabled={saving}
                  style={{ width: '2.5em', height: '1.25em', cursor: 'pointer' }}
                />
                <label className="form-check-label small text-muted">Enable system logging</label>
              </div>
            </div>

            <div className="mb-3">
              <label className="form-label small fw-semibold">WHMCS API URL</label>
              <input
                type="url"
                className="form-control form-control-sm"
                name="whmcs_api_url"
                value={form.whmcs_api_url ?? ''}
                onChange={handleChange}
                disabled={saving}
                placeholder="https://yourdomain.com"
              />
              <div className="form-text">
                Base URL of your WHMCS installation — e.g. <code>https://burstnet.com.au</code>. The licensing endpoint is appended automatically. Leave blank to skip license validation (grace mode).
              </div>
            </div>

            <div className="mb-0">
              <label className="form-label small fw-semibold">WHMCS License Key</label>
              <div className="form-text mb-2">
                To enable HMAC signature verification, add the following to your <code>wp-config.php</code> (before the <em>"That's all, stop editing!"</em> line):
              </div>
              <pre className="bg-light border rounded px-3 py-2 mb-2" style={{ fontSize: '0.78rem' }}>
                {`define( 'SARAH_AI_WHMCS_LICENSE_SECRET', 'your-secret-key-here' );`}
              </pre>
              <div className="form-check form-switch">
                <input
                  className="form-check-input"
                  type="checkbox"
                  name="whmcs_key_required"
                  checked={form.whmcs_key_required === '1'}
                  onChange={handleChange}
                  disabled={saving}
                  style={{ width: '2.5em', height: '1.25em', cursor: 'pointer' }}
                />
                <label className="form-check-label small text-muted">
                  Require WHMCS key on Quick Setup — rejects provisioning requests without a valid license key
                </label>
              </div>
              <div className="form-check form-switch mt-2">
                <input
                  className="form-check-input"
                  type="checkbox"
                  name="allow_platform_openai_key"
                  checked={form.allow_platform_openai_key === '1'}
                  onChange={handleChange}
                  disabled={saving}
                  style={{ width: '2.5em', height: '1.25em', cursor: 'pointer' }}
                />
                <label className="form-check-label small text-muted">
                  Allow platform OpenAI key as fallback — sites without their own key will use the platform's shared key
                </label>
              </div>
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
