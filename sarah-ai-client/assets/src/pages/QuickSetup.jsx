import React, { useState } from 'react';
import { apiFetch } from '../api/client.js';

/**
 * QuickSetup — full-page wizard shown when the plugin is not yet configured.
 *
 * Flow:
 *   1. User enters Server URL + Platform Key + (optional) WHMCS key
 *   2. POST to server's /quick-setup endpoint → get account_key + site_key
 *   3. Save credentials via client's /widget-settings endpoint
 *   4. Reload page → admin panel opens normally
 */
export default function QuickSetup() {
  const cfg = window.SarahAiClientConfig || {};

  // If config.php defines these constants, use them and hide the fields.
  const fixedServerUrl   = cfg.serverUrl   || '';
  const fixedPlatformKey = cfg.platformKey || '';

  const [step, setStep]       = useState('form'); // 'form' | 'loading' | 'success' | 'error'
  const [form, setForm]       = useState({
    server_url:      fixedServerUrl,
    platform_key:    fixedPlatformKey,
    whmcs_key:       '',
    openai_api_key:  '',
  });
  const [result, setResult]   = useState(null);
  const [errorMsg, setErrorMsg] = useState('');

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.server_url || !form.platform_key) return;

    setStep('loading');
    setErrorMsg('');

    try {
      const serverBase = form.server_url.replace(/\/$/, '');

      // Step 1: Call server via local PHP proxy (avoids CORS)
      const serverData = await apiFetch('connect', 'POST', {
        server_url:     serverBase,
        platform_key:   form.platform_key,
        site_name:      cfg.siteName || 'My Site',
        site_url:       cfg.siteUrl  || window.location.origin,
        whmcs_key:      form.whmcs_key,
        openai_api_key: form.openai_api_key,
      });

      if (!serverData.success) {
        setErrorMsg(serverData.message || 'Server returned an error. Check your Server URL and Platform Key.');
        setStep('error');
        return;
      }

      // Step 2: Save credentials to client settings
      await apiFetch('widget-settings', 'POST', {
        server_url:   serverBase + '/sarah-ai-server/v1',
        account_key:  serverData.data.account_key,
        site_key:     serverData.data.site_key,
        platform_key: form.platform_key,
      });

      setResult(serverData.data);
      setStep('success');

    } catch (err) {
      setErrorMsg('Could not connect to the server. Check the Server URL and try again.');
      setStep('error');
    }
  }

  if (step === 'success') {
    return (
      <div className="d-flex align-items-center justify-content-center" style={{ minHeight: '100vh', background: '#f4f6fb' }}>
        <div className="card border-0 shadow" style={{ maxWidth: 480, width: '100%' }}>
          <div className="card-body p-5 text-center">
            <div className="mb-3">
              <span style={{ fontSize: 48 }}>✅</span>
            </div>
            <h4 className="fw-bold mb-1">All set!</h4>
            <p className="text-muted small mb-2">
              Your Sarah AI chat widget is configured and ready.
            </p>
            <div className="d-flex flex-wrap justify-content-center gap-2 mb-3">
              <span className="badge bg-primary-subtle text-primary border border-primary-subtle">
                Plan: {result?.plan ?? 'trial'}
              </span>
              <span className="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                Agent: {result?.agent_slug ?? '—'}
              </span>
              {result?.has_openai_key && (
                <span className="badge bg-success-subtle text-success border border-success-subtle">OpenAI key saved</span>
              )}
              {result?.has_kb && (
                <span className="badge bg-info-subtle text-info border border-info-subtle">KB entry created</span>
              )}
            </div>
            <button
              className="btn btn-primary w-100"
              onClick={() => window.location.reload()}
            >
              Open Dashboard
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="d-flex align-items-center justify-content-center" style={{ minHeight: '100vh', background: '#f4f6fb' }}>
      <div className="card border-0 shadow" style={{ maxWidth: 480, width: '100%' }}>

        {/* Header */}
        <div className="card-header bg-dark text-white py-4 text-center border-0">
          <h4 className="fw-bold mb-1">Sarah AI — Quick Setup</h4>
          <p className="text-white-50 small mb-0">Connect this site to the Sarah AI platform in one step.</p>
        </div>

        <div className="card-body p-4">

          {step === 'error' && (
            <div className="alert alert-danger small py-2 px-3 mb-3">{errorMsg}</div>
          )}

          <form onSubmit={handleSubmit}>

            {/* Server URL — hidden when pre-configured via config.php */}
            {!fixedServerUrl && (
              <div className="mb-3">
                <label className="form-label fw-semibold small">Server URL <span className="text-danger">*</span></label>
                <input
                  type="url"
                  className="form-control form-control-sm"
                  name="server_url"
                  value={form.server_url}
                  onChange={handleChange}
                  placeholder="https://your-server.example.com/wp-json"
                  required
                  disabled={step === 'loading'}
                />
                <div className="form-text">Base WordPress REST API URL of the sarah-ai-server installation.</div>
              </div>
            )}

            {/* Platform Key — hidden when pre-configured via config.php */}
            {!fixedPlatformKey && (
              <div className="mb-3">
                <label className="form-label fw-semibold small">Platform Key <span className="text-danger">*</span></label>
                <input
                  type="password"
                  className="form-control form-control-sm font-monospace"
                  name="platform_key"
                  value={form.platform_key}
                  onChange={handleChange}
                  placeholder="Provided by your platform administrator"
                  autoComplete="new-password"
                  required
                  disabled={step === 'loading'}
                />
                <div className="form-text">Used to authenticate this setup request with the server.</div>
              </div>
            )}

            {/* WHMCS Key (required) */}
            <div className="mb-3">
              <label className="form-label fw-semibold small">WHMCS License Key <span className="text-danger">*</span></label>
              <input
                type="password"
                className="form-control form-control-sm font-monospace"
                name="whmcs_key"
                value={form.whmcs_key}
                onChange={handleChange}
                placeholder="Your WHMCS license key"
                autoComplete="new-password"
                required
                disabled={step === 'loading'}
              />
              <div className="form-text">Required to activate your service.</div>
            </div>

            {/* OpenAI API Key (required) */}
            <div className="mb-4">
              <label className="form-label fw-semibold small">OpenAI API Key <span className="text-danger">*</span></label>
              <input
                type="password"
                className="form-control form-control-sm font-monospace"
                name="openai_api_key"
                value={form.openai_api_key}
                onChange={handleChange}
                placeholder="sk-…"
                autoComplete="new-password"
                required
                disabled={step === 'loading'}
              />
              <div className="form-text">Chat messages will be billed to this key.</div>
            </div>

            <button
              type="submit"
              className="btn btn-primary w-100"
              disabled={step === 'loading' || !form.server_url || !form.platform_key || !form.whmcs_key || !form.openai_api_key}
            >
              {step === 'loading' ? (
                <><span className="spinner-border spinner-border-sm me-2" />Connecting…</>
              ) : (
                'Connect & Activate'
              )}
            </button>

          </form>
        </div>

        <div className="card-footer bg-transparent border-top text-center py-3">
          <span className="text-muted small">
            Site: <strong>{cfg.siteName || window.location.hostname}</strong>
          </span>
        </div>
      </div>
    </div>
  );
}
