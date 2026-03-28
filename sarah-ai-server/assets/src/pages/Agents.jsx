import React, { useState, useEffect } from 'react';
import { listAgents, updateAgentBehavior } from '../api/provisioning.js';
import FieldBox from '../components/FieldBox.jsx';

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
  const [allowGeneral,          setAllowGeneral]         = useState(config.allow_general_knowledge ?? true);
  const [noClosing,             setNoClosing]            = useState(config.no_closing_question     ?? true);
  const [handleVague,           setHandleVague]          = useState(config.handle_vague_queries    ?? true);
  const [customRules,           setCustomRules]          = useState(config.custom_rules            ?? '');
  const [knowledgeInstruction,  setKnowledgeInstruction] = useState(config.knowledge_instruction  ?? '');
  const [knowledgeFallback,     setKnowledgeFallback]    = useState(config.knowledge_fallback      ?? '');
  const [restrictedResponse,    setRestrictedResponse]   = useState(config.restricted_response     ?? '');
  const [maxTokens,             setMaxTokens]            = useState(config.max_tokens  ?? '');
  const [temperature,           setTemperature]          = useState(config.temperature ?? '');

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
      allow_general_knowledge: allowGeneral,
      no_closing_question:     noClosing,
      handle_vague_queries:    handleVague,
      custom_rules:            customRules,
      knowledge_instruction:   knowledgeInstruction,
      knowledge_fallback:      knowledgeFallback,
      restricted_response:     restrictedResponse,
      max_tokens:              maxTokens === '' ? null : Number(maxTokens),
      temperature:             temperature === '' ? null : Number(temperature),
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

        {/* ── Model ───────────────────────────────────────── */}
        <FieldBox label={`Model: ${config.model ?? '—'}`}>
          <div className="row g-3">
            <div className="col-md-auto">
              <label className="form-label small fw-semibold">Max Tokens</label>
              <input
                type="number" min="256" max="16000" step="256"
                className="form-control form-control-sm"
                style={{ width: '120px' }}
                value={maxTokens}
                onChange={e => setMaxTokens(e.target.value)}
                disabled={saving}
              />
            </div>
            <div className="col-md-auto">
              <label className="form-label small fw-semibold">Temperature</label>
              <input
                type="number" min="0" max="2" step="0.1"
                className="form-control form-control-sm"
                style={{ width: '100px' }}
                value={temperature}
                onChange={e => setTemperature(e.target.value)}
                disabled={saving}
              />
            </div>
          </div>
        </FieldBox>

        {/* ── Identity & Role ─────────────────────────────── */}
        <FieldBox label="Identity & Role">
          <div className="row g-3">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Tone Preset</label>
              <select className="form-select form-select-sm" value={tone} onChange={e => setTone(e.target.value)} disabled={saving}>
                {TONES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Role</label>
              <input
                type="text" className="form-control form-control-sm"
                value={role} onChange={e => setRole(e.target.value)}
                placeholder="e.g. customer support assistant"
                disabled={saving}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">Custom Tone Text</label>
              <input
                type="text" className="form-control form-control-sm"
                value={toneCustom} onChange={e => setToneCustom(e.target.value)}
                placeholder="e.g. Speak in a fun, energetic tone…"
                disabled={saving}
              />
            </div>
          </div>
        </FieldBox>

        {/* ── Behaviour Rules ──────────────────────────────── */}
        <FieldBox label="Behaviour Rules">
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
          <div className="form-check">
            <input className="form-check-input" type="checkbox" id="chk-closing"
              checked={noClosing} onChange={e => setNoClosing(e.target.checked)} disabled={saving} />
            <label className="form-check-label small" htmlFor="chk-closing">
              No closing question — do not end responses with "Is there anything else…"
            </label>
          </div>
        </FieldBox>

        {/* ── Custom Rules ─────────────────────────────────── */}
        <FieldBox label="Custom Rules">
          <textarea
            className="form-control form-control-sm"
            rows={3}
            value={customRules}
            onChange={e => setCustomRules(e.target.value)}
            placeholder={"One rule per line. Each line is added as a bullet point to the Behaviour Rules section.\ne.g. Always recommend booking an appointment for complex questions."}
            disabled={saving}
          />
        </FieldBox>

        {/* ── Knowledge Base ───────────────────────────────── */}
        <FieldBox label="Knowledge Base">
          <div className="mb-3">
            <label className="form-label small fw-semibold">Knowledge Instruction</label>
            <textarea
              className="form-control form-control-sm" rows={2}
              value={knowledgeInstruction} onChange={e => setKnowledgeInstruction(e.target.value)}
              disabled={saving}
            />
          </div>
          <div className="mb-3">
            <label className="form-label small fw-semibold">Knowledge Fallback</label>
            <textarea
              className="form-control form-control-sm" rows={2}
              value={knowledgeFallback} onChange={e => setKnowledgeFallback(e.target.value)}
              disabled={saving}
            />
          </div>
          <div>
            <label className="form-label small fw-semibold">Restricted Info Response</label>
            <input
              type="text" className="form-control form-control-sm"
              value={restrictedResponse} onChange={e => setRestrictedResponse(e.target.value)}
              disabled={saving}
            />
          </div>
        </FieldBox>

        <div className="d-flex justify-content-end align-items-center gap-3 mt-1">
          <button type="submit" className="btn btn-primary orange-bg px-4" disabled={saving}>
            {saving ? 'Saving…' : 'Save'}
          </button>
          {saved && <span className="text-success small">Saved.</span>}
          {error && <span className="text-danger small">{error}</span>}
        </div>

      </form>
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
          <div className="card-header p-0">
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
