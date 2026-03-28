import React, { useState } from 'react';
import { quickCreateTenant } from '../api/provisioning.js';
import FieldBox from '../components/FieldBox.jsx';

const AGENT_OPTIONS = [
  { slug: 'gpt-4o-mini', label: 'OpenAI — GPT-4o Mini' },
];

const EMPTY_FORM = {
  site_url:      '',
  whmcs_key:     '',
  openai_api_key: '',
  agent_slug:    'gpt-4o-mini',
};

export default function CreateTenant({ onNavigate }) {
  const [form, setForm]       = useState(EMPTY_FORM);
  const [saving, setSaving]   = useState(false);
  const [error, setError]     = useState(null);
  const [created, setCreated] = useState(null);

  function set(key, val) {
    setForm(prev => ({ ...prev, [key]: val }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const res = await quickCreateTenant({
        site_url:       form.site_url.trim(),
        whmcs_key:      form.whmcs_key.trim(),
        openai_api_key: form.openai_api_key.trim(),
        agent_slug:     form.agent_slug,
      });
      if (res.success) {
        setCreated(res.data);
        setForm(EMPTY_FORM);
      } else {
        setError(res.message ?? 'Failed to create tenant.');
      }
    } catch {
      setError('Request failed.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="row justify-content-center">
      <div className="col-lg-7">

        <div className="d-flex justify-content-end mb-3">
          <button className="btn btn-sm btn-outline-secondary" onClick={() => onNavigate('tenants')}>
            ← Tenants
          </button>
        </div>

        <div className="card shadow-sm">
          <div className="card-header bg-white">
            <h5 className="fw-semibold mb-0">Create New Tenant</h5>
            <p className="text-muted small mb-0 mt-1">
              Everything is provisioned automatically — keys, agent, and knowledge base seed.
            </p>
          </div>

          <div className="card-body">
            {created ? (
              <div>
                <div className="alert alert-success">
                  <strong>{created.tenant_name}</strong> provisioned successfully.
                  Copy the keys below — they will <strong>not</strong> be shown again.
                </div>
                <table className="table table-sm table-bordered mb-4">
                  <tbody>
                    <tr>
                      <td className="text-muted small fw-semibold" style={{ width: '140px' }}>Account Key</td>
                      <td className="font-monospace small user-select-all">{created.account_key}</td>
                    </tr>
                    <tr>
                      <td className="text-muted small fw-semibold">Site Key</td>
                      <td className="font-monospace small user-select-all">{created.site_key}</td>
                    </tr>
                    <tr>
                      <td className="text-muted small fw-semibold">Agent</td>
                      <td className="small">{created.agent_slug}</td>
                    </tr>
                  </tbody>
                </table>
                <div className="d-flex gap-2">
                  <button
                    className="btn btn-primary btn-sm"
                    onClick={() => onNavigate('tenant-detail', created.tenant_uuid)}
                  >
                    Open Tenant →
                  </button>
                  <button className="btn btn-outline-secondary btn-sm" onClick={() => setCreated(null)}>
                    Create Another
                  </button>
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => onNavigate('tenants')}>
                    ← Tenants
                  </button>
                </div>
              </div>
            ) : (
              <form onSubmit={handleSubmit}>
                <FieldBox label="Site URL *" hint="Must be unique. Site name is fetched automatically from the page title.">
                  <input
                    type="url"
                    className="form-control field-box-control"
                    placeholder="https://example.com"
                    value={form.site_url}
                    onChange={e => set('site_url', e.target.value)}
                    disabled={saving}
                    required
                  />
                </FieldBox>

                <FieldBox label="WHMCS License Key *" hint="Validated against WHMCS before provisioning. Must be active.">
                  <input
                    type="password"
                    className="form-control field-box-control font-monospace"
                    placeholder="License key"
                    autoComplete="new-password"
                    value={form.whmcs_key}
                    onChange={e => set('whmcs_key', e.target.value)}
                    disabled={saving}
                    required
                  />
                </FieldBox>

                <FieldBox label="OpenAI API Key *" hint="Stored as the site's own OpenAI key. Used for all AI requests on this site.">
                  <input
                    type="password"
                    className="form-control field-box-control font-monospace"
                    placeholder="sk-..."
                    autoComplete="new-password"
                    value={form.openai_api_key}
                    onChange={e => set('openai_api_key', e.target.value)}
                    disabled={saving}
                    required
                  />
                </FieldBox>

                <FieldBox label="Agent">
                  <select
                    className="form-select field-box-control"
                    value={form.agent_slug}
                    onChange={e => set('agent_slug', e.target.value)}
                    disabled={saving}
                  >
                    {AGENT_OPTIONS.map(a => (
                      <option key={a.slug} value={a.slug}>{a.label}</option>
                    ))}
                  </select>
                </FieldBox>

                {error && <div className="alert alert-danger py-2 mb-3">{error}</div>}

                <div className="d-flex gap-2">
                  <button type="submit" className="btn btn-primary" disabled={saving}>
                    {saving ? 'Provisioning…' : 'Create Tenant'}
                  </button>
                  <button
                    type="button"
                    className="btn btn-outline-secondary"
                    onClick={() => onNavigate('tenants')}
                    disabled={saving}
                  >
                    Cancel
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>

      </div>
    </div>
  );
}
