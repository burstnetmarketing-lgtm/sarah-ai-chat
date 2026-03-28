import { useState } from 'react';
import { assignAgent, unassignAgent } from '../../api/provisioning.js';

function Alert({ type, msg }) {
  if (!msg) return null;
  return <div className={`alert alert-${type} py-1 px-2 small mt-2 mb-0`}>{msg}</div>;
}

export default function AssignmentCard({ site, agents, onAssigned }) {
  const [editing, setEditing]   = useState(false);
  const [selected, setSelected] = useState('');
  const [saving, setSaving]     = useState(false);
  const [msg, setMsg]           = useState(null);

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
              <button className="btn btn-primary btn-sm" onClick={handleAssign} disabled={saving || !selected}>
                {saving ? '…' : 'Save'}
              </button>
              <button className="btn btn-outline-secondary btn-sm" onClick={() => setEditing(false)} disabled={saving}>
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
              <button className="btn btn-outline-danger btn-sm" onClick={handleUnassign} disabled={saving}>
                Remove
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
