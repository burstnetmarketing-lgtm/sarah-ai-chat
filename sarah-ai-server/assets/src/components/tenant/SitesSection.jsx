import React, { useState, useEffect, useRef } from 'react';
import {
  updateSiteStatus, createSite,
  listSiteKeys, createSiteKey, deleteSiteKey,
} from '../../api/provisioning.js';
import { Alert, StatusBadge } from './helpers.jsx';

export default function SitesSection({ tenantUuid, sites, agents, onReload, onNavigate, onKeysChange }) {
  const [addExpanded, setAddExpanded] = useState(false);
  const [addForm, setAddForm]         = useState({ name: '', url: '' });
  const [addSaving, setAddSaving]     = useState(false);
  const [addMsg, setAddMsg]           = useState(null);
  const [togglingUuid, setTog]        = useState(null);
  const [siteKeysMap, setSiteKeysMap] = useState({});
  const siteKeysRef = useRef({});
  siteKeysRef.current = siteKeysMap;

  function getSiteData(uuid) {
    return siteKeysRef.current[uuid] ?? { keys: [], rawKey: null, form: { label: '' }, saving: false, formExpanded: false };
  }

  function updateSiteData(uuid, patch) {
    setSiteKeysMap(prev => {
      const current = prev[uuid] ?? { keys: [], rawKey: null, form: { label: '' }, saving: false, formExpanded: false };
      return { ...prev, [uuid]: { ...current, ...patch } };
    });
  }

  function loadSiteKeys(uuid) {
    listSiteKeys(uuid).then(res => {
      if (res.success) {
        updateSiteData(uuid, { keys: res.data });
        if (uuid === sites[0]?.uuid) onKeysChange?.(res.data);
      }
    }).catch(() => {});
  }

  useEffect(() => {
    sites.forEach(site => loadSiteKeys(site.uuid));
  }, [sites.map(s => s.uuid).join(',')]);

  async function handleCreate(e) {
    e.preventDefault();
    if (!addForm.name.trim() || !addForm.url.trim()) return;
    setAddSaving(true); setAddMsg(null);
    try {
      const res = await createSite({ tenant_uuid: tenantUuid, name: addForm.name.trim(), url: addForm.url.trim() });
      if (res.success) { setAddForm({ name: '', url: '' }); setAddExpanded(false); onReload(); }
      else setAddMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setAddMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setAddSaving(false); }
  }

  async function handleToggleStatus(site) {
    const next = site.status === 'active' ? 'inactive' : 'active';
    setTog(site.uuid);
    try { const res = await updateSiteStatus(site.uuid, next); if (res.success) onReload(); }
    catch {} finally { setTog(null); }
  }

  async function handleIssueKey(siteUuid, label, e) {
    e.preventDefault();
    updateSiteData(siteUuid, { saving: true, rawKey: null });
    try {
      const res = await createSiteKey(siteUuid, { label: label.trim() || 'default' });
      if (res.success) {
        updateSiteData(siteUuid, { rawKey: res.data.raw_key, form: { label: '' }, formExpanded: false });
        loadSiteKeys(siteUuid);
      }
    } catch { alert('Request failed.'); }
    finally { updateSiteData(siteUuid, { saving: false }); }
  }

  async function handleRevokeKey(siteUuid, keyUuid) {
    if (!confirm('Revoke this site key?')) return;
    try { await deleteSiteKey(keyUuid); loadSiteKeys(siteUuid); }
    catch { alert('Failed.'); }
  }

  async function handleRegenerateKey(siteUuid, key) {
    if (!confirm('Revoke current key and issue a new one?')) return;
    updateSiteData(siteUuid, { saving: true, rawKey: null });
    try {
      await deleteSiteKey(key.uuid);
      const res = await createSiteKey(siteUuid, { label: key.label || 'default' });
      if (res.success) updateSiteData(siteUuid, { rawKey: res.data.raw_key });
      loadSiteKeys(siteUuid);
    } catch { alert('Request failed.'); }
    finally { updateSiteData(siteUuid, { saving: false }); }
  }

  return (
    <div className="card border-0 shadow-sm">
      <div className="card-header">
        <div className="d-flex justify-content-between align-items-center">
          <div>
            <h6 className="fw-semibold mb-0">
              Sites
              <span className="badge bg-light text-dark border ms-2">{sites.length}</span>
            </h6>
            <p className="text-muted small mb-0">Register sites and manage their API keys.</p>
          </div>
          <div className="d-flex gap-2">
            <button className="btn btn-outline-secondary btn-sm" onClick={onReload}>↺</button>
            <button className="btn btn-outline-primary btn-sm" onClick={() => { setAddExpanded(e => !e); setAddMsg(null); }}>
              {addExpanded ? '− Cancel' : '+ Add Site'}
            </button>
          </div>
        </div>
      </div>
      <div className="card-body">
        {addExpanded && (
          <form onSubmit={handleCreate} className="mb-3 p-3 bg-light rounded">
            <div className="row g-2 align-items-end">
              <div className="col-md-5">
                <input className="form-control form-control-sm" placeholder="Site Name *"
                  value={addForm.name} onChange={e => setAddForm(f => ({ ...f, name: e.target.value }))} required />
              </div>
              <div className="col-md-5">
                <input className="form-control form-control-sm" placeholder="https://example.com"
                  value={addForm.url} onChange={e => setAddForm(f => ({ ...f, url: e.target.value }))} required />
              </div>
              <div className="col-md-2">
                <button className="btn btn-primary btn-sm w-100 orange-bg" type="submit" disabled={addSaving}>
                  {addSaving ? '…' : 'Register'}
                </button>
              </div>
            </div>
            {addMsg && <Alert type={addMsg.type} msg={addMsg.text} />}
          </form>
        )}

        {sites.length === 0 ? (
          <p className="text-muted small mb-0">No sites registered yet.</p>
        ) : (
          <table className="table table-sm mb-0">
            <thead className="table-light">
              <tr><th>Name</th><th>URL</th><th>Status</th><th>Agent</th><th></th></tr>
            </thead>
            <tbody>
              {sites.map(site => {
                const agent      = agents.find(a => a.id == site.active_agent_id);
                const isToggling = togglingUuid === site.uuid;
                const sd         = getSiteData(site.uuid);
                return (
                  <React.Fragment key={site.uuid ?? site.id}>
                    <tr>
                      <td className="fw-semibold">{site.name}</td>
                      <td className="text-muted small">{site.url}</td>
                      <td>
                        <button
                          className={`btn btn-sm py-0 ${site.status === 'active' ? 'btn-success' : 'btn-outline-secondary'}`}
                          style={{ fontSize: '0.75rem' }}
                          onClick={() => handleToggleStatus(site)}
                          disabled={isToggling}
                          title={site.status === 'active' ? 'Click to deactivate' : 'Click to activate'}
                        >
                          {isToggling ? '…' : site.status}
                        </button>
                      </td>
                      <td>
                        <button
                          className="btn btn-sm btn-outline-primary py-0"
                          style={{ fontSize: '0.75rem' }}
                          onClick={() => onNavigate('site-agent', `${tenantUuid}/${site.uuid}`)}
                        >
                          {agent ? agent.name : '— assign'}
                        </button>
                      </td>
                      <td className="text-end">
                        <button
                          className="btn btn-sm btn-outline-secondary py-0"
                          style={{ fontSize: '0.75rem' }}
                          onClick={() => updateSiteData(site.uuid, { formExpanded: !sd.formExpanded, rawKey: null })}
                        >
                          {sd.formExpanded ? '− Key' : '+ Key'}
                        </button>
                      </td>
                    </tr>

                    {sd.formExpanded && (
                      <tr className="table-light">
                        <td colSpan={5} className="py-2 px-3">
                          <form onSubmit={e => handleIssueKey(site.uuid, sd.form.label, e)} className="d-flex gap-2 align-items-center">
                            <input
                              className="form-control form-control-sm"
                              style={{ maxWidth: '200px' }}
                              placeholder="Label (default)"
                              value={sd.form.label}
                              onChange={e => updateSiteData(site.uuid, { form: { label: e.target.value } })}
                              disabled={sd.saving}
                            />
                            <button className="btn btn-sm orange-bg btn-primary" type="submit" disabled={sd.saving}>
                              {sd.saving ? '…' : 'Issue Key'}
                            </button>
                          </form>
                        </td>
                      </tr>
                    )}

                    {sd.rawKey && (
                      <tr>
                        <td colSpan={5} className="py-1 px-3">
                          <div className="alert alert-warning py-2 px-3 mb-0">
                            <strong>Copy now — shown once.</strong>
                            <div className="font-monospace mt-1 user-select-all small bg-body border rounded px-2 py-1">{sd.rawKey}</div>
                          </div>
                        </td>
                      </tr>
                    )}

                    {sd.keys?.map(k => (
                      <tr key={k.uuid} style={{ background: '#f8f9fa' }}>
                        <td className="ps-4 small text-muted" colSpan={2}>⤷ {k.label}</td>
                        <td><StatusBadge status={k.status} map={{ active: 'success', revoked: 'danger' }} /></td>
                        <td></td>
                        <td className="text-end">
                          <div className="d-flex gap-1 justify-content-end">
                            {k.status === 'active' && (
                              <>
                                <button className="btn btn-sm btn-outline-secondary py-0"
                                  onClick={() => handleRegenerateKey(site.uuid, k)} disabled={sd.saving}>Regenerate</button>
                                <button className="btn btn-sm btn-outline-danger py-0"
                                  onClick={() => handleRevokeKey(site.uuid, k.uuid)}>Revoke</button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </React.Fragment>
                );
              })}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
