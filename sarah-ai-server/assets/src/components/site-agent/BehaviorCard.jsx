import { useState, useEffect } from 'react';
import { getSiteAgentConfig, updateSiteAgentConfig } from '../../api/provisioning.js';

const BEHAVIOR_EMPTY = {
  tone: '', tone_custom: '',
  allow_general_knowledge: '', no_closing_question: '', handle_vague_queries: '',
  custom_rules: '', knowledge_instruction: '', knowledge_fallback: '',
  restricted_response: '',
};

export default function BehaviorCard({ siteUuid }) {
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
        <p className="text-muted small mb-0 mt-1">Per-site overrides. Leave blank to inherit the agent's default.</p>
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
              { key: 'allow_general_knowledge', label: 'Allow General Knowledge', hint: 'Let agent answer questions outside the knowledge base.' },
              { key: 'no_closing_question',     label: 'No Closing Question',     hint: 'Suppress the end-of-reply follow-up question.' },
              { key: 'handle_vague_queries',    label: 'Handle Vague Queries',    hint: 'Give helpful prompts when the user message is unclear.' },
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
            <div className="d-flex justify-content-end align-items-center gap-3">
              {saved && <span className="text-success small">Saved.</span>}
              {error && <span className="text-danger small">{error}</span>}
              <button type="submit" className="btn btn-primary orange-bg px-4" disabled={saving}>
                {saving ? 'Saving…' : 'Save Overrides'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
