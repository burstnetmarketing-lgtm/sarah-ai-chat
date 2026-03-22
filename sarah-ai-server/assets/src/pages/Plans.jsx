import React, { useState, useEffect, useCallback } from 'react';
import { listPlans, listAgents, syncPlanAgents } from '../api/provisioning.js';

export default function Plans() {
  const [plans, setPlans]   = useState([]);
  const [agents, setAgents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(null); // plan id being saved
  const [msg, setMsg]         = useState({});    // { [planId]: { type, text } }

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([listPlans(), listAgents()])
      .then(([plansRes, agentsRes]) => {
        if (plansRes.success)  setPlans(plansRes.data);
        if (agentsRes.success) setAgents(agentsRes.data);
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  function getAllowedIds(plan) {
    return (plan.agents ?? []).map(a => a.id);
  }

  function toggleAgent(plan, agentId) {
    const current = getAllowedIds(plan);
    const updated = current.includes(agentId)
      ? current.filter(id => id !== agentId)
      : [...current, agentId];

    setPlans(prev => prev.map(p => p.id === plan.id
      ? { ...p, agents: agents.filter(a => updated.includes(a.id)) }
      : p
    ));
  }

  async function handleSave(plan) {
    setSaving(plan.id);
    setMsg(m => ({ ...m, [plan.id]: null }));
    try {
      const agentIds = getAllowedIds(plan);
      const res = await syncPlanAgents(plan.id, agentIds);
      setMsg(m => ({ ...m, [plan.id]: { type: res.success ? 'success' : 'danger', text: res.success ? 'Saved.' : (res.message ?? 'Failed.') } }));
    } catch {
      setMsg(m => ({ ...m, [plan.id]: { type: 'danger', text: 'Request failed.' } }));
    } finally {
      setSaving(null);
    }
  }

  if (loading) return <p className="text-muted p-4">Loading…</p>;

  return (
    <div className="p-4">
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 className="fw-bold mb-0">Plans</h4>
          <p className="text-muted small mb-0">Configure which AI agents each plan can access.</p>
        </div>
      </div>

      {plans.length === 0 ? (
        <p className="text-muted">No plans found.</p>
      ) : (
        <div className="row g-3">
          {plans.map(plan => {
            const allowedIds = getAllowedIds(plan);
            const m = msg[plan.id];
            return (
              <div key={plan.id} className="col-md-6 col-lg-4">
                <div className="card border-0 shadow-sm h-100">
                  <div className="card-header d-flex justify-content-between align-items-center">
                    <div>
                      <h6 className="fw-bold mb-0">{plan.name}</h6>
                      <span className="text-muted small">{plan.slug}</span>
                    </div>
                    <span className="badge bg-secondary">{plan.duration_days}d</span>
                  </div>
                  <div className="card-body">
                    <p className="text-muted small mb-2 fw-semibold">Allowed Agents</p>
                    {agents.length === 0 ? (
                      <p className="text-muted small">No agents available.</p>
                    ) : (
                      <div className="d-flex flex-column gap-2 mb-3">
                        {agents.map(agent => (
                          <div key={agent.id} className="form-check">
                            <input
                              type="checkbox"
                              className="form-check-input"
                              id={`plan-${plan.id}-agent-${agent.id}`}
                              checked={allowedIds.includes(agent.id)}
                              onChange={() => toggleAgent(plan, agent.id)}
                            />
                            <label className="form-check-label" htmlFor={`plan-${plan.id}-agent-${agent.id}`}>
                              <span className="fw-semibold">{agent.name}</span>
                              {agent.description && (
                                <div className="text-muted" style={{ fontSize: '11px' }}>{agent.description}</div>
                              )}
                            </label>
                          </div>
                        ))}
                      </div>
                    )}
                    {m && (
                      <div className={`alert alert-${m.type} py-1 px-2 small mb-2`}>{m.text}</div>
                    )}
                    <button
                      className="btn btn-primary btn-sm w-100"
                      onClick={() => handleSave(plan)}
                      disabled={saving === plan.id}
                    >
                      {saving === plan.id ? 'Saving…' : 'Save'}
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
