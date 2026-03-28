import { useState, useEffect, useCallback, useRef } from 'react';
import {
  getTenant, listAccountKeys, listAvailableAgents,
  listSiteKeys, listKnowledge,
  markTenantSetupComplete, deleteTenant,
} from '../api/provisioning.js';
import { PrereqCard } from '../components/tenant/helpers.jsx';
import ReadinessCheck    from '../components/tenant/ReadinessCheck.jsx';
import TenantInfoPanel   from '../components/tenant/TenantInfoPanel.jsx';
import LicensePanel      from '../components/tenant/LicensePanel.jsx';
import UsersSection      from '../components/tenant/UsersSection.jsx';
import SitesSection      from '../components/tenant/SitesSection.jsx';
import AccountKeysSection from '../components/tenant/AccountKeysSection.jsx';
import KnowledgeSection  from '../components/tenant/KnowledgeSection.jsx';

function LaunchPanel({ allOk }) {
  return (
    <div className="card border-0 shadow-sm">
      <div className="card-body text-center py-5">
        {allOk ? (
          <>
            <div style={{ fontSize: '52px', color: '#198754', lineHeight: 1 }}>✓</div>
            <h5 className="fw-bold text-success mt-3 mb-1">All steps complete!</h5>
            <p className="text-muted small">This tenant is fully configured and ready for integration.</p>
            <span className="badge bg-success px-3 py-2">Ready to Launch</span>
          </>
        ) : (
          <>
            <div style={{ fontSize: '52px', color: '#dee2e6', lineHeight: 1 }}>○</div>
            <h6 className="text-muted mt-3 mb-1">Not ready yet</h6>
            <p className="text-muted small">Complete all previous steps to unlock launch readiness.</p>
          </>
        )}
      </div>
    </div>
  );
}

const STEP_TITLES = ['Tenant', 'License', 'Users', 'Sites', 'Account Keys', 'Knowledge', 'Launch'];
const LAST_STEP   = STEP_TITLES.length - 1; // 6

export default function TenantDetail({ param, onNavigate }) {
  const tenantUuid = param;

  const [tenant, setTenant]           = useState(null);
  const [sites, setSites]             = useState([]);
  const [users, setUsers]             = useState([]);
  const [accountKeys, setAccountKeys] = useState([]);
  const [agentsList, setAgentsList]   = useState([]);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState(null);
  const [activeStep, setActiveStep]   = useState(null);
  const [deleting, setDeleting]       = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  const [firstSiteKeys, setFirstSiteKeys]                   = useState([]);
  const [firstSiteKnowledgeCount, setFirstSiteKnowledgeCount] = useState(0);

  const stepInitialized = useRef(false);
  const setupMarkedRef  = useRef(false);
  const firstSiteUuid   = sites[0]?.uuid ?? null;

  const steps = [
    { label: 'Tenant',      sub: 'Active',     ok: !!tenant && tenant.status === 'active' },
    { label: 'License',     sub: 'Set',        ok: !!(tenant?.whmcs_key) },
    { label: 'Users',       sub: 'Added',      ok: users.length > 0 },
    { label: 'Sites',       sub: 'Ready',      ok: sites.length > 0 && firstSiteKeys.length > 0 },
    { label: 'Account Key', sub: 'Issued',     ok: accountKeys.length > 0 },
    { label: 'Knowledge',   sub: 'Added',      ok: firstSiteKnowledgeCount > 0 },
    { label: 'Launch',      sub: 'Ready',      ok: false },
  ];
  steps[LAST_STEP].ok = steps.slice(0, LAST_STEP).every(s => s.ok);

  const load = useCallback(async () => {
    if (!tenantUuid) return;
    setLoading(true);
    try {
      const [tenantRes, keysRes, agentsRes] = await Promise.all([
        getTenant(tenantUuid),
        listAccountKeys(tenantUuid),
        listAvailableAgents(tenantUuid),
      ]);

      if (!tenantRes.success) { setError('Tenant not found.'); return; }

      const loadedSites = tenantRes.data.sites ?? [];
      setTenant(tenantRes.data.tenant);
      setSites(loadedSites);
      setUsers(tenantRes.data.users ?? []);
      if (keysRes.success) setAccountKeys(keysRes.data);
      if (agentsRes.success) setAgentsList(agentsRes.data);

      const fsuuid = loadedSites[0]?.uuid ?? null;
      if (fsuuid) {
        const [siteKeysRes, knowledgeRes] = await Promise.all([
          listSiteKeys(fsuuid),
          listKnowledge(fsuuid),
        ]);
        if (siteKeysRes.success) setFirstSiteKeys(siteKeysRes.data);
        if (knowledgeRes.success) setFirstSiteKnowledgeCount((knowledgeRes.data ?? []).length);
      }
    } catch {
      setError('Failed to load tenant.');
    } finally {
      setLoading(false);
    }
  }, [tenantUuid]);

  useEffect(() => { load(); }, [load]);

  const handleDelete = useCallback(async () => {
    if (!window.confirm(`Delete tenant "${tenant?.name}" and ALL related data? This cannot be undone.`)) return;
    setDeleting(true); setDeleteError(null);
    try {
      const res = await deleteTenant(tenantUuid);
      if (res.success) { onNavigate('tenants'); return; }
      setDeleteError(res.message ?? 'Delete failed.');
    } catch {
      setDeleteError('Delete failed.');
    } finally {
      setDeleting(false);
    }
  }, [tenant, tenantUuid, onNavigate]);

  const allOk = steps[LAST_STEP].ok;

  useEffect(() => {
    if (allOk && tenant && !tenant.setup_complete && !setupMarkedRef.current) {
      setupMarkedRef.current = true;
      markTenantSetupComplete(tenantUuid).catch(() => {});
    }
  }, [allOk, tenant]);

  const isEditMode = !!(tenant?.setup_complete || allOk);

  useEffect(() => {
    if (!loading && !stepInitialized.current) {
      stepInitialized.current = true;
      if (isEditMode) {
        setActiveStep(0);
      } else {
        const firstIncomplete = steps.findIndex(s => !s.ok);
        setActiveStep(firstIncomplete >= 0 ? firstIncomplete : LAST_STEP);
      }
    }
  }, [loading, isEditMode]);

  function renderStepContent() {
    if (loading || activeStep === null) return <p className="text-muted small">Loading…</p>;
    switch (activeStep) {
      case 0: return <TenantInfoPanel tenant={tenant} />;
      case 1: return <LicensePanel tenant={tenant} tenantUuid={tenantUuid} onReload={load} />;
      case 2: return <UsersSection tenantUuid={tenantUuid} onReload={load} />;
      case 3: return (
        <SitesSection
          tenantUuid={tenantUuid}
          sites={sites}
          agents={agentsList}
          onReload={load}
          onNavigate={onNavigate}
          onKeysChange={keys => setFirstSiteKeys(keys)}
        />
      );
      case 4: return <AccountKeysSection tenantUuid={tenantUuid} onKeysChange={keys => setAccountKeys(keys)} />;
      case 5: return firstSiteUuid
        ? <KnowledgeSection siteUuid={firstSiteUuid} onItemsChange={items => setFirstSiteKnowledgeCount(items.length)} />
        : <PrereqCard msg="Register a site in Step 4 (Sites) first." />;
      case 6: return <LaunchPanel allOk={steps[LAST_STEP].ok} />;
      default: return null;
    }
  }

  if (!tenantUuid) return <p className="text-danger">Invalid tenant.</p>;

  return (
    <div className="row">
      <div className="col-12">

        {/* Page header */}
        <div className="mb-3 d-flex align-items-start justify-content-between">
          <div>
            {loading ? (
              <span className="text-muted small">Loading…</span>
            ) : error ? (
              <span className="text-danger">{error}</span>
            ) : (
              <>
                <h4 className="fw-semibold mb-0">{tenant?.name}</h4>
                <div className="mt-1 d-flex align-items-center gap-2 flex-wrap">
                  <span className="text-muted small">{tenant?.slug}</span>
                  <span className={`badge bg-${tenant?.status === 'active' ? 'success-subtle text-success' : 'secondary-subtle text-secondary'}`}>
                    {tenant?.status}
                  </span>
                  {tenant?.whmcs_key
                    ? <span className="badge bg-success-subtle text-success">Customer</span>
                    : <span className="badge bg-info-subtle text-info">Trial</span>
                  }
                  {isEditMode && <span className="badge bg-success-subtle text-success">✓ Setup Complete</span>}
                </div>
              </>
            )}
          </div>
          <div className="d-flex justify-content-end gap-2">
            <button className="btn btn-sm btn-outline-secondary" onClick={() => onNavigate('tenants')}>← Tenants</button>
            {tenant && (
              <button className="btn btn-sm btn-outline-danger" onClick={handleDelete} disabled={deleting}>
                {deleting ? 'Deleting…' : 'Delete Tenant'}
              </button>
            )}
          </div>
          {deleteError && <div className="text-danger small mt-1">{deleteError}</div>}
        </div>

        {!loading && !error && (
          isEditMode ? (
            /* ── Edit Mode: Tabbed card ─────────────────────────── */
            <div className="card">
              <div className="card-header p-0 border-bottom-0">
                <ul className="nav nav-tabs card-header-tabs px-3 pt-2" style={{ flexWrap: 'nowrap', overflowX: 'auto' }}>
                  {STEP_TITLES.map((title, i) => (
                    <li key={i} className="nav-item" style={{ whiteSpace: 'nowrap' }}>
                      <button className={`nav-link ${activeStep === i ? 'active' : ''}`} onClick={() => setActiveStep(i)}>
                        {title}
                      </button>
                    </li>
                  ))}
                </ul>
              </div>
              <div className="card-body">
                {renderStepContent()}
              </div>
            </div>
          ) : (
            /* ── Setup Mode: Stepper + Prev/Next ────────────────── */
            <>
              <ReadinessCheck steps={steps} activeStep={activeStep} onStepClick={setActiveStep} />

              {activeStep !== null && (
                <div className="d-flex align-items-center gap-2 mb-3">
                  <span className="badge bg-primary">Step {activeStep + 1}</span>
                  <span className="fw-semibold">{STEP_TITLES[activeStep]}</span>
                </div>
              )}

              {renderStepContent()}

              <div className="d-flex justify-content-between align-items-center mt-3 mb-4">
                <button
                  className="btn btn-outline-secondary btn-sm"
                  onClick={() => setActiveStep(s => Math.max(0, (s ?? 0) - 1))}
                  disabled={activeStep === 0}
                >
                  ← Previous
                </button>
                <div className="d-flex align-items-center gap-2">
                  {activeStep !== null && !steps[activeStep]?.ok && activeStep !== LAST_STEP && (
                    <span className="text-warning small fw-semibold">Complete this step to continue</span>
                  )}
                  <button
                    className="btn btn-primary btn-sm"
                    onClick={() => setActiveStep(s => Math.min(LAST_STEP, (s ?? 0) + 1))}
                    disabled={activeStep === LAST_STEP || (activeStep !== null && !steps[activeStep]?.ok)}
                  >
                    Next →
                  </button>
                </div>
              </div>
            </>
          )
        )}

      </div>
    </div>
  );
}
