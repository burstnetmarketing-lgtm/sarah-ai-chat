import React, { useState } from 'react';
import { apiFetch } from '../api/client.js';
import sarahImg from '../images/sarah.webp';

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
    <div className="d-flex align-items-center justify-content-center" style={{ minHeight: '100vh', background: '#f4f6fb', padding: '0 16px' }}>

      <div className="card border-0 shadow" style={{ maxWidth: 860, width: '100%', overflow: 'hidden' }}>

        {/* Header — full width */}
        <div className="bg-dark text-white py-4 text-center">
          <h4 className="fw-bold mb-1" style={{ color: '#ffc107' }}>Sarah AI — Quick Setup</h4>
          <p className="text-white-50 small mb-0">Connect this site to the Sarah AI platform in one step.</p>
        </div>

        {/* Body — image left + form right */}
        <div className="d-flex" style={{ alignItems: 'stretch' }}>

          {/* Image */}
          <div className="d-none d-lg-block" style={{ width: 400, flexShrink: 0 }}>
            <img
              src={sarahImg}
              alt="Sarah AI"
              style={{ width: '100%', height: '100%', objectFit: 'cover', objectPosition: 'center top', display: 'block' }}
            />
          </div>

          {/* Form */}
          <div className="p-4 flex-grow-1">

            {step === 'error' && (
              <div className="alert alert-danger small py-2 px-3 mb-3">{errorMsg}</div>
            )}

            <form onSubmit={handleSubmit}>

              {/* Server URL — hidden when pre-configured via config.php */}
              {!fixedServerUrl && (
                <div className="form-floating mb-3">
                  <input
                    type="url"
                    className="form-control"
                    id="server_url"
                    name="server_url"
                    value={form.server_url}
                    onChange={handleChange}
                    placeholder="https://your-server.example.com/wp-json"
                    required
                    disabled={step === 'loading'}
                  />
                  <label htmlFor="server_url">Server URL <span className="text-danger">*</span></label>
                </div>
              )}

              {/* Platform Key — hidden when pre-configured via config.php */}
              {!fixedPlatformKey && (
                <div className="form-floating mb-3">
                  <input
                    type="password"
                    className="form-control font-monospace"
                    id="platform_key"
                    name="platform_key"
                    value={form.platform_key}
                    onChange={handleChange}
                    placeholder="Provided by your platform administrator"
                    autoComplete="new-password"
                    required
                    disabled={step === 'loading'}
                  />
                  <label htmlFor="platform_key">Platform Key <span className="text-danger">*</span></label>
                </div>
              )}

              {/* Site Name — display only */}
              <div className="form-floating mb-1">
                <input
                  type="text"
                  className="form-control bg-light"
                  id="siteName"
                  value={cfg.siteName || window.location.hostname}
                  placeholder="Site Name"
                  readOnly
                  tabIndex={-1}
                />
                <label htmlFor="siteName">Site Name</label>
              </div>
              <div className="form-text ps-1 mb-3">Your WordPress site name, detected automatically.</div>

              {/* Sarah License Key (required) */}
              <div className="form-floating mb-1">
                <input
                  type="password"
                  className="form-control font-monospace"
                  id="whmcs_key"
                  name="whmcs_key"
                  value={form.whmcs_key}
                  onChange={handleChange}
                  placeholder="Your Sarah license key"
                  autoComplete="new-password"
                  required
                  disabled={step === 'loading'}
                />
                <label htmlFor="whmcs_key">Sarah License Key <span className="text-danger">*</span></label>
              </div>
              <div className="form-text ps-1 mb-3">Required to activate your service.</div>

              {/* OpenAI API Key (required) */}
              <div className="form-floating mb-1">
                <input
                  type="password"
                  className="form-control font-monospace"
                  id="openai_api_key"
                  name="openai_api_key"
                  value={form.openai_api_key}
                  onChange={handleChange}
                  placeholder="sk-…"
                  autoComplete="new-password"
                  required
                  disabled={step === 'loading'}
                />
                <label htmlFor="openai_api_key">OpenAI API Key <span className="text-danger">*</span></label>
              </div>
              <div className="form-text ps-1 mb-4">Billed to your own OpenAI account.</div>

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
        </div>

        {/* Footer — full width */}
        <div className="border-top text-center py-3 bg-transparent">
          <span className="text-muted small">
            Provided by{' '}
            <a href="https://burstnet.com.au/" target="_blank" rel="noopener noreferrer" className="text-decoration-none">
              BurstNET
            </a>
          </span>
        </div>

      </div>
    </div>
  );
}
