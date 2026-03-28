import { useState, useEffect } from 'react';
import { getAgentIdentity, updateAgentIdentity } from '../../api/provisioning.js';

export default function IdentityCard({ siteUuid }) {
  const [form, setForm]       = useState({ agent_display_name: '', greeting_message: '', intro_message: '' });
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
        <p className="text-muted small mb-0 mt-1">How the agent presents itself — name, greeting, introduction.</p>
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
            <div className="d-flex justify-content-end align-items-center gap-3">
              {saved  && <span className="text-success small">Saved.</span>}
              {error  && <span className="text-danger small">{error}</span>}
              <button type="submit" className="btn btn-primary orange-bg px-4" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
