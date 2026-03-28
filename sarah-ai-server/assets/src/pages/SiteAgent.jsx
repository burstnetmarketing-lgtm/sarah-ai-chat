import { useState, useEffect, useCallback } from 'react';
import { getSite, listAvailableAgents } from '../api/provisioning.js';
import AssignmentCard from '../components/site-agent/AssignmentCard.jsx';
import IdentityCard   from '../components/site-agent/IdentityCard.jsx';
import BehaviorCard   from '../components/site-agent/BehaviorCard.jsx';

export default function SiteAgent({ param, onNavigate }) {
  // param = "tenantUuid/siteUuid"
  const [tenantUuid, siteUuid] = param ? param.split('/') : ['', ''];

  const [site, setSite]       = useState(null);
  const [agents, setAgents]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState(null);

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

  if (!siteUuid) return <p className="text-danger">Invalid site.</p>;

  return (
    <div className="row justify-content-center">
      <div className="col-lg-8">

        <div className="d-flex justify-content-end mb-3">
          <button className="btn btn-sm btn-outline-secondary" onClick={() => onNavigate('tenant-detail', tenantUuid)}>
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
