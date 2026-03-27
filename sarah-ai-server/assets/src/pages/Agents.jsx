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

  const [role,                  setRole]                 = useState(config.role                    ?? '');
  const [tone,                  setTone]                 = useState(config.tone                    ?? '');
  const [toneCustom,            setToneCustom]           = useState(config.tone_custom             ?? '');
  const [systemPrompt,          setSystemPrompt]         = useState(config.system_prompt           ?? '');
  const [allowGeneral,          setAllowGeneral]         = useState(config.allow_general_knowledge ?? true);
  const [noClosing,             setNoClosing]            = useState(config.no_closing_question     ?? true);
  const [handleVague,           setHandleVague]          = useState(config.handle_vague_queries    ?? true);
  const [customRules,           setCustomRules]          = useState(config.custom_rules            ?? '');
  const [knowledgeInstruction,  setKnowledgeInstruction] = useState(config.knowledge_instruction  ?? '');
  const [knowledgeFallback,     setKnowledgeFallback]    = useState(config.knowledge_fallback      ?? '');
  const [restrictedResponse,    setRestrictedResponse]   = useState(config.restricted_response     ?? '');

  const [saving, setSaving] = useState(false);
  const [saved,  setSaved]  = useState(false);
  const [error,  setError]  = useState(null);

  function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    setSaved(false);
    setError(null);
    updateAgentBehavior(agent.id, {
      role,
      tone,
      tone_custom:             toneCustom,
      system_prompt:           systemPrompt,
      allow_general_knowledge: allowGeneral,
      no_closing_question:     noClosing,
      handle_vague_queries:    handleVague,
      custom_rules:            customRules,
      knowledge_instruction:   knowledgeInstruction,
      knowledge_fallback:      knowledgeFallback,
      restricted_response:     restrictedResponse,
    })
      .then(res => {
        if (!res.success) throw new Error('Save failed.');
        setSaved(true);
        onSaved?.(res.data);
      })
      .catch(err => setError(err.message))
      .finally(() => setSaving(false));
  }

  return (
    <div className="p-3">
      {agent.description && (
        <p className="text-muted small mb-3">{agent.description}</p>
      )}

      <form onSubmit={handleSave}>

        {/* ── Identity & Role ─────────────────────────────── */}
        <h6 className="fw-semibold text-secondary small text-uppercase mb-2">Identity &amp; Role</h6>
        <div className="row g-3 mb-4">
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
            <div className="form-text">Defines what the agent does.</div>
          </div>
          <div className="col-md-3">
            <label className="form-label small fw-semibold">Tone Preset</label>
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
          <div className="col-md-12">
            <label className="form-label small fw-semibold">
              Custom Tone Text
              <span className="text-muted fw-normal ms-2">(overrides preset)</span>
            </label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={toneCustom}
              onChange={e => setToneCustom(e.target.value)}
              placeholder="e.g. Speak in a fun, energetic tone suitable for a youth brand."
              disabled={saving}
            />
          </div>
        </div>

        {/* ── Behaviour Rules ──────────────────────────────── */}
        <h6 className="fw-semibold text-secondary small text-uppercase mb-2">Behaviour Rules</h6>
        <div className="mb-3">
          <div className="form-check mb-1">
            <input className="form-check-input" type="checkbox" id="chk-general"
              checked={allowGeneral} onChange={e => setAllowGeneral(e.target.checked)} disabled={saving} />
            <label className="form-check-label small" htmlFor="chk-general">
              Allow general knowledge — AI can answer using world knowledge, not only the Knowledge Base
            </label>
          </div>
          <div className="form-check mb-1">
            <input className="form-check-input" type="checkbox" id="chk-vague"
              checked={handleVague} onChange={e => setHandleVague(e.target.checked)} disabled={saving} />
            <label className="form-check-label small" htmlFor="chk-vague">
              Handle vague queries — treat single-word or short messages as broad questions
            </label>
          </div>
          <div className="form-check mb-2">
            <input className="form-check-input" type="checkbox" id="chk-closing"
              checked={noClosing} onChange={e => setNoClosing(e.target.checked)} disabled={saving} />
            <label className="form-check-label small" htmlFor="chk-closing">
              No closing question — do not end responses with "Is there anything else…"
            </label>
          </div>
          <label className="form-label small fw-semibold">Custom Rules</label>
          <textarea
            className="form-control form-control-sm"
            rows={3}
            value={customRules}
            onChange={e => setCustomRules(e.target.value)}
            placeholder={"One rule per line. Each line is added as a bullet point to the Behaviour Rules section.\ne.g. Always recommend booking an appointment for complex questions."}
            disabled={saving}
          />
        </div>

        {/* ── Knowledge Base ───────────────────────────────── */}
        <h6 className="fw-semibold text-secondary small text-uppercase mb-2">Knowledge Base</h6>
        <div className="row g-3 mb-4">
          <div className="col-md-6">
            <label className="form-label small fw-semibold">
              Knowledge Instruction
              <span className="text-muted fw-normal ms-2">(how to present KB content)</span>
            </label>
            <textarea
              className="form-control form-control-sm"
              rows={2}
              value={knowledgeInstruction}
              onChange={e => setKnowledgeInstruction(e.target.value)}
              placeholder="Present this information in a clear, helpful, and organized way. Use it to answer questions accurately."
              disabled={saving}
            />
          </div>
          <div className="col-md-6">
            <label className="form-label small fw-semibold">
              Knowledge Fallback
              <span className="text-muted fw-normal ms-2">(when KB is empty)</span>
            </label>
            <textarea
              className="form-control form-control-sm"
              rows={2}
              value={knowledgeFallback}
              onChange={e => setKnowledgeFallback(e.target.value)}
              placeholder="No business-specific information has been provided…"
              disabled={saving}
            />
          </div>
          <div className="col-md-12">
            <label className="form-label small fw-semibold">
              Restricted Info Response
              <span className="text-muted fw-normal ms-2">(when user asks for info not in KB)</span>
            </label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={restrictedResponse}
              onChange={e => setRestrictedResponse(e.target.value)}
              placeholder="I'm sorry, I don't have that information available right now…"
              disabled={saving}
            />
          </div>
        </div>

        {/* ── Full Override ────────────────────────────────── */}
        <h6 className="fw-semibold text-secondary small text-uppercase mb-2">Full Prompt Override</h6>
        <div className="mb-3">
          <label className="form-label small fw-semibold">
            Custom System Prompt
            <span className="text-muted fw-normal ms-2">(replaces everything above)</span>
          </label>
          <textarea
            className="form-control form-control-sm font-monospace"
            rows={5}
            value={systemPrompt}
            onChange={e => setSystemPrompt(e.target.value)}
            placeholder={"If set, this replaces the auto-composed prompt entirely.\nLeave blank to use role + tone + guardrails."}
            disabled={saving}
          />
          <div className="form-text mt-1">
            {systemPrompt.trim()
              ? <span className="badge bg-warning text-dark">Custom prompt active — all sections above are ignored</span>
              : <span className="text-muted small">Composed from sections above</span>}
          </div>
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
