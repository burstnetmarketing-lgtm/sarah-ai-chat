import React, { useState, useEffect, useCallback } from 'react';
import {
  getSite,
  listAvailableAgents,
  assignAgent, unassignAgent,
  getAgentIdentity, updateAgentIdentity,
  getSiteAgentConfig, updateSiteAgentConfig,
} from '../api/provisioning.js';

// ─── Helpers ──────────────────────────────────────────────────────────────────

function Alert({ type, msg }) {
  if (!msg) return null;
  return <div className={`alert alert-${type} py-1 px-2 small mt-2 mb-0`}>{msg}</div>;
}

// ─── Agent Assignment ─────────────────────────────────────────────────────────

function AssignmentCard({ site, agents, onAssigned }) {
  const [editing, setEditing]       = useState(false);
  const [selected, setSelected]     = useState('');
  const [saving, setSaving]         = useState(false);
  const [msg, setMsg]               = useState(null);

  const agent = agents.find(a => a.id == site?.active_agent_id);

  function startEdit() {
    setSelected(site?.active_agent_id ? String(site.active_agent_id) : '');
    setEditing(true);
    setMsg(null);
  }

  async function handleAssign() {
    if (!selected) return;
    setSaving(true); setMsg(null);
    try {
      const res = await assignAgent(site.uuid, parseInt(selected, 10));
      if (res.success) { setEditing(false); onAssigned?.(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  async function handleUnassign() {
    if (!confirm('Remove agent from this site?')) return;
    setSaving(true); setMsg(null);
    try {
      const res = await unassignAgent(site.uuid);
      if (res.success) { setEditing(false); onAssigned?.(); }
      else setMsg({ type: 'danger', text: res.message ?? 'Failed.' });
    } catch { setMsg({ type: 'danger', text: 'Request failed.' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header">
        <h6 className="fw-semibold mb-0">Agent Assignment</h6>
        <p className="text-muted small mb-0">The AI agent that handles chat for this site.</p>
      </div>
      <div className="card-body">
        {editing ? (
          <div>
            <select className="form-select form-select-sm mb-2" style={{ maxWidth: 320 }}
              value={selected} onChange={e => setSelected(e.target.value)}>
              <option value="">— select agent —</option>
              {agents.map(a => (
                <option key={a.id} value={a.id}>{a.name} ({a.slug})</option>
              ))}
            </select>
            <div className="d-flex gap-2">
              <button className="btn btn-primary btn-sm" onClick={handleAssign}
                disabled={saving || !selected}>
                {saving ? '…' : 'Save'}
              </button>
              <button className="btn btn-outline-secondary btn-sm"
                onClick={() => setEditing(false)} disabled={saving}>
                Cancel
              </button>
            </div>
            {msg && <Alert type={msg.type} msg={msg.text} />}
          </div>
        ) : (
          <div className="d-flex align-items-center gap-3">
            {agent
              ? <span className="badge bg-success-subtle text-success fs-6 px-3 py-2">{agent.name}</span>
              : <span className="text-muted small">No agent assigned</span>
            }
            <button className="btn btn-outline-primary btn-sm" onClick={startEdit}>
              {agent ? 'Change' : 'Assign'}
            </button>
            {agent && (
              <button className="btn btn-outline-danger btn-sm"
                onClick={handleUnassign} disabled={saving}>
                Remove
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

// ─── Agent Identity ───────────────────────────────────────────────────────────

function IdentityCard({ siteUuid }) {
  const [form, setForm]   = useState({ agent_display_name: '', greeting_message: '', intro_message: '' });
  const [loading, setLoading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [saved,   setSaved]   = useState(false);
  const [error,   setError]   = useState(null);

  useEffect(() => {
    setLoading(true);
    getAgentIdentity(siteUuid)
      .then(res => {
        if (res.success) setForm({
          agent_display_name: res.data.agent_display_name ?? '',
          greeting_message:   res.data.greeting_message   ?? '',
          intro_message:      res.data.intro_message      ?? '',
        });
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [siteUuid]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true); setSaved(false); setError(null);
    updateAgentIdentity(siteUuid, form)
      .then(res => { if (!res.success) throw new Error('Save failed.'); setSaved(true); })
      .catch(err => setError(err.message))
      .finally(() => setSaving(false));
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header">
        <h6 className="mb-0 fw-semibold">Agent Identity</h6>
        <p className="text-muted small mb-0 mt-1">
          How the agent presents itself — name, greeting, introduction.
        </p>
      </div>
      <div className="card-body">
        {loading ? <p className="text-muted small">Loading…</p> : (
          <form onSubmit={handleSave}>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Display Name</label>
              <input type="text" name="agent_display_name" className="form-control form-control-sm"
                value={form.agent_display_name} onChange={handleChange}
                placeholder="e.g. Sarah" disabled={saving} />
              <div className="form-text">Injected into the system prompt as the agent's name.</div>
            </div>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Intro Message</label>
              <input type="text" name="intro_message" className="form-control form-control-sm"
                value={form.intro_message} onChange={handleChange}
                placeholder="e.g. I am Sarah, your assistant." disabled={saving} />
              <div className="form-text">Injected into the system prompt as the agent's self-introduction.</div>
            </div>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Greeting Message</label>
              <input type="text" name="greeting_message" className="form-control form-control-sm"
                value={form.greeting_message} onChange={handleChange}
                placeholder="e.g. Hi! How can I help you today?" disabled={saving} />
              <div className="form-text">Shown when the widget opens. Not part of the AI prompt.</div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
              </button>
              {saved  && <span className="text-success small">Saved.</span>}
              {error  && <span className="text-danger small">{error}</span>}
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

// ─── Agent Behavior ───────────────────────────────────────────────────────────

const BEHAVIOR_EMPTY = {
  tone: '', tone_custom: '',
  allow_general_knowledge: '', no_closing_question: '', handle_vague_queries: '',
  custom_rules: '', knowledge_instruction: '', knowledge_fallback: '',
  restricted_response: '', system_prompt: '',
};

function BehaviorCard({ siteUuid }) {
  const [form, setForm]       = useState(BEHAVIOR_EMPTY);
  const [loading, setLoading] = useState(true);
  const [saving,  setSaving]  = useState(false);
  const [saved,   setSaved]   = useState(false);
  const [error,   setError]   = useState(null);

  useEffect(() => {
    setLoading(true);
    getSiteAgentConfig(siteUuid)
      .then(res => {
        if (res.success) {
          const d = res.data ?? {};
          setForm({
            tone:                    d.tone                    ?? '',
            tone_custom:             d.tone_custom             ?? '',
            allow_general_knowledge: d.allow_general_knowledge != null ? String(d.allow_general_knowledge) : '',
            no_closing_question:     d.no_closing_question     != null ? String(d.no_closing_question)     : '',
            handle_vague_queries:    d.handle_vague_queries    != null ? String(d.handle_vague_queries)    : '',
            custom_rules:            d.custom_rules            ?? '',
            knowledge_instruction:   d.knowledge_instruction   ?? '',
            knowledge_fallback:      d.knowledge_fallback      ?? '',
            restricted_response:     d.restricted_response     ?? '',
            system_prompt:           d.system_prompt           ?? '',
          });
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [siteUuid]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  function handleSave(e) {
    e.preventDefault();
    setSaving(true); setSaved(false); setError(null);
    const boolFields = ['allow_general_knowledge', 'no_closing_question', 'handle_vague_queries'];
    const payload = {};
    Object.entries(form).forEach(([key, val]) => {
      payload[key] = boolFields.includes(key)
        ? (val === '' ? null : val === 'true')
        : (val === '' ? null : val);
    });
    updateSiteAgentConfig(siteUuid, payload)
      .then(res => { if (!res.success) throw new Error('Save failed.'); setSaved(true); })
      .catch(err => setError(err.message))
      .finally(() => setSaving(false));
  }

  return (
    <div className="card border-0 shadow-sm mb-3">
      <div className="card-header">
        <h6 className="mb-0 fw-semibold">Behavior Overrides</h6>
        <p className="text-muted small mb-0 mt-1">
          Per-site overrides. Leave blank to inherit the agent's default.
        </p>
      </div>
      <div className="card-body">
        {loading ? <p className="text-muted small">Loading…</p> : (
          <form onSubmit={handleSave}>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Tone</label>
              <select className="form-select form-select-sm" name="tone" value={form.tone} onChange={handleChange} disabled={saving}>
                <option value="">— use agent default —</option>
                <option value="professional">Professional</option>
                <option value="friendly">Friendly</option>
                <option value="formal">Formal</option>
                <option value="casual">Casual</option>
                <option value="custom">Custom</option>
              </select>
            </div>
            {form.tone === 'custom' && (
              <div className="mb-3">
                <label className="form-label small fw-semibold">Custom Tone</label>
                <input type="text" name="tone_custom" className="form-control form-control-sm"
                  value={form.tone_custom} onChange={handleChange}
                  placeholder="Describe the tone…" disabled={saving} />
              </div>
            )}
            {[
              { key: 'allow_general_knowledge', label: 'Allow General Knowledge',  hint: 'Let agent answer questions outside the knowledge base.' },
              { key: 'no_closing_question',     label: 'No Closing Question',       hint: 'Suppress the end-of-reply follow-up question.' },
              { key: 'handle_vague_queries',    label: 'Handle Vague Queries',      hint: 'Give helpful prompts when the user message is unclear.' },
            ].map(({ key, label, hint }) => (
              <div className="mb-3" key={key}>
                <label className="form-label small fw-semibold">{label}</label>
                <select className="form-select form-select-sm" name={key} value={form[key]} onChange={handleChange} disabled={saving}>
                  <option value="">— use agent default —</option>
                  <option value="true">Enabled</option>
                  <option value="false">Disabled</option>
                </select>
                <div className="form-text">{hint}</div>
              </div>
            ))}
            <div className="mb-3">
              <label className="form-label small fw-semibold">Custom Rules</label>
              <textarea rows={3} name="custom_rules" className="form-control form-control-sm"
                value={form.custom_rules} onChange={handleChange}
                placeholder="Leave blank to use agent default" disabled={saving} />
              <div className="form-text">Extra behavioral rules appended to the system prompt.</div>
            </div>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Knowledge Instruction</label>
              <textarea rows={2} name="knowledge_instruction" className="form-control form-control-sm"
                value={form.knowledge_instruction} onChange={handleChange}
                placeholder="Leave blank to use agent default" disabled={saving} />
              <div className="form-text">How the agent should use the knowledge base.</div>
            </div>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Knowledge Fallback</label>
              <input type="text" name="knowledge_fallback" className="form-control form-control-sm"
                value={form.knowledge_fallback} onChange={handleChange}
                placeholder="Leave blank to use agent default" disabled={saving} />
              <div className="form-text">What to say when no relevant knowledge is found.</div>
            </div>
            <div className="mb-3">
              <label className="form-label small fw-semibold">Restricted Response</label>
              <input type="text" name="restricted_response" className="form-control form-control-sm"
                value={form.restricted_response} onChange={handleChange}
                placeholder="Leave blank to use agent default" disabled={saving} />
              <div className="form-text">Response when the topic is outside allowed scope.</div>
            </div>
            <div className="mb-4">
              <label className="form-label small fw-semibold">Full System Prompt Override</label>
              <textarea rows={4} name="system_prompt" className="form-control form-control-sm"
                value={form.system_prompt} onChange={handleChange}
                placeholder="Leave blank to use agent default" disabled={saving} />
              <div className="form-text">Replaces the agent's base system prompt entirely for this site.</div>
            </div>
            <div className="d-flex align-items-center gap-2">
              <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save Overrides'}
              </button>
              {saved && <span className="text-success small">Saved.</span>}
              {error && <span className="text-danger small">{error}</span>}
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

// ─── Page ──────────────────────────────────────────────────────────────────────

export default function SiteAgent({ param, onNavigate }) {
  // param = "tenantUuid/siteUuid"
  const [tenantUuid, siteUuid] = param ? param.split('/') : ['', ''];

  const [site, setSite]         = useState(null);
  const [agents, setAgents]     = useState([]);
  const [loading, setLoading]   = useState(true);
  const [error, setError]       = useState(null);

  const load = useCallback(async () => {
    if (!siteUuid) return;
    setLoading(true);
    try {
      const [siteRes, agentsRes] = await Promise.all([
        getSite(siteUuid),
        listAvailableAgents(tenantUuid),
      ]);
      if (!siteRes.success) { setError('Site not found.'); return; }
      setSite(siteRes.data.site);
      if (agentsRes.success) setAgents(agentsRes.data);
    } catch {
      setError('Failed to load site.');
    } finally {
      setLoading(false);
    }
  }, [siteUuid, tenantUuid]);

  useEffect(() => { load(); }, [load]);

  const backToTenant = () => onNavigate('tenant-detail', tenantUuid);

  if (!siteUuid) return <p className="text-danger">Invalid site.</p>;

  return (
    <div className="row justify-content-center">
      <div className="col-lg-8">

        <div className="d-flex align-items-center gap-2 mb-3">
          <button className="btn btn-link p-0 text-muted small" onClick={backToTenant}>
            ← Back
          </button>
        </div>

        {loading ? (
          <p className="text-muted small">Loading…</p>
        ) : error ? (
          <p className="text-danger">{error}</p>
        ) : (
          <>
            <div className="mb-4">
              <h5 className="fw-semibold mb-0">{site.name}</h5>
              <div className="text-muted small">{site.url}</div>
            </div>

            <AssignmentCard site={site} agents={agents} onAssigned={load} />
            <IdentityCard siteUuid={siteUuid} />
            <BehaviorCard siteUuid={siteUuid} />
          </>
        )}

      </div>
    </div>
  );
}
