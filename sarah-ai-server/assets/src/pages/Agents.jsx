import React, { useState, useEffect } from 'react';
import { listAgents, updateAgentBehavior } from '../api/provisioning.js';

const TONES = [
  { value: '',             label: '— Default —' },
  { value: 'friendly',    label: 'Friendly' },
  { value: 'professional',label: 'Professional' },
  { value: 'concise',     label: 'Concise' },
  { value: 'formal',      label: 'Formal' },
];

function AgentPanel({ agent, onSaved }) {
  const config = (() => {
    try { return typeof agent.config === 'string' ? JSON.parse(agent.config) : (agent.config || {}); }
    catch { return {}; }
  })();

  const [role,         setRole]         = useState(config.role          ?? '');
  const [tone,         setTone]         = useState(config.tone          ?? '');
  const [systemPrompt, setSystemPrompt] = useState(config.system_prompt ?? '');
  const [saving,       setSaving]       = useState(false);
  const [saved,        setSaved]        = useState(false);
  const [error,        setError]        = useState(null);

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    setError(null);
    updateAgentBehavior(agent.id, { role, tone, system_prompt: systemPrompt })
      .then(res => {
        if (!res.success) throw new Error('Save failed.');
        setSaved(true);
        onSaved?.(res.data);
      })
      .catch(err => setError(err.message))
      .finally(() => setSaving(false));
  }

  const promptPreview = systemPrompt.trim()
    ? <span className="badge bg-success">Custom prompt active</span>
    : <span className="text-muted small">Composed from role + tone + guardrails</span>;

  return (
    <div className="p-3">
      {agent.description && (
        <p className="text-muted small mb-3">{agent.description}</p>
      )}

      <form onSubmit={handleSave}>
        <div className="row g-3 mb-3">
          <div className="col-md-5">
            <label className="form-label small fw-semibold">Role</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={role}
              onChange={e => setRole(e.target.value)}
              placeholder="e.g. customer support assistant, sales agent"
              disabled={saving}
            />
            <div className="form-text">Defines what the agent does. Used in the system prompt.</div>
          </div>
          <div className="col-md-3">
            <label className="form-label small fw-semibold">Tone</label>
            <select
              className="form-select form-select-sm"
              value={tone}
              onChange={e => setTone(e.target.value)}
              disabled={saving}
            >
              {TONES.map(t => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="mb-3">
          <label className="form-label small fw-semibold">
            Custom System Prompt
            <span className="text-muted fw-normal ms-2">(optional override)</span>
          </label>
          <textarea
            className="form-control form-control-sm font-monospace"
            rows={6}
            value={systemPrompt}
            onChange={e => setSystemPrompt(e.target.value)}
            placeholder={"If set, this replaces the auto-composed prompt entirely.\nLeave blank to use role + tone + guardrails."}
            disabled={saving}
          />
          <div className="form-text mt-1">{promptPreview}</div>
        </div>

        <div className="d-flex align-items-center gap-2">
          <button type="submit" className="btn btn-sm btn-primary" disabled={saving}>
            {saving ? 'Saving…' : 'Save'}
          </button>
          {saved && <span className="text-success small">Saved.</span>}
          {error && <span className="text-danger small">{error}</span>}
        </div>
      </form>

      <div className="mt-3 pt-3 border-top d-flex gap-3">
        {config.model       && <span className="text-muted small">Model: <strong>{config.model}</strong></span>}
        {config.max_tokens  && <span className="text-muted small">Max tokens: <strong>{config.max_tokens}</strong></span>}
        {config.temperature !== undefined && <span className="text-muted small">Temperature: <strong>{config.temperature}</strong></span>}
      </div>
    </div>
  );
}

export default function Agents() {
  const [agents,  setAgents]  = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState(null);
  const [activeTab, setActiveTab] = useState(0);

  useEffect(() => {
    listAgents()
      .then(res => {
        if (!res.success) throw new Error('Failed to load agents.');
        setAgents(res.data);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  function handleSaved(updated) {
    setAgents(prev => prev.map(a => a.id === updated.id ? updated : a));
  }

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Agents</h1>
        <p className="text-muted small mb-0">Configure agent behavior — role, tone, and system prompt.</p>
      </div>

      {loading && <p className="text-muted small">Loading…</p>}
      {error   && <p className="text-danger small">{error}</p>}

      {!loading && !error && agents.length === 0 && (
        <p className="text-muted small">No active agents found.</p>
      )}

      {!loading && !error && agents.length > 0 && (
        <div className="card border-0 shadow-sm">
          <div className="card-header bg-white border-bottom p-0">
            <ul className="nav nav-tabs border-0 px-2 pt-2">
              {agents.map((agent, i) => (
                <li key={agent.id} className="nav-item">
                  <button
                    className={`nav-link${activeTab === i ? ' active' : ''}`}
                    onClick={() => setActiveTab(i)}
                  >
                    {agent.name}
                    <span className={`badge ms-2 ${agent.status === 'active' ? 'bg-success' : 'bg-secondary'}`}
                      style={{ fontSize: '0.65rem' }}>
                      {agent.status}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
          <AgentPanel key={agents[activeTab].id} agent={agents[activeTab]} onSaved={handleSaved} />
        </div>
      )}
    </>
  );
}
