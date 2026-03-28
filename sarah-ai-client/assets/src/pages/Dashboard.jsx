import { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';
import { getSessions, getSiteStats } from '../api/sessionsApi.js';
import sarahImg from '../images/sarah.webp';

const QUICK_LINKS = [
  { label: 'Appearance',      desc: 'Customise widget colours, avatar and position.',  view: 'appearance'      },
  { label: 'Quick Questions', desc: 'Manage suggested questions shown in the widget.',  view: 'quick-questions' },
  { label: 'Knowledge Base',  desc: 'Add documents and URLs for the AI to reference.', view: 'knowledge-base'  },
  { label: 'URL Blacklist',   desc: 'Block the widget from appearing on specific pages.', view: 'url-blacklist'   },
  { label: 'Settings',        desc: 'Toggle the widget and set greeting message.',      view: 'settings'        },
];

export default function Dashboard({ onNavigate }) {
  const [settings, setSettings]   = useState(null);
  const [toggling, setToggling]   = useState(false);
  const [sessions, setSessions]   = useState([]);
  const [sessionsLoading, setSessLoading] = useState(true);
  const [stats, setStats]         = useState({ total_sessions: null, total_messages: null });

  useEffect(() => {
    apiFetch('widget-settings')
      .then(res => { if (res.success) setSettings(res.data); })
      .catch(() => {});

    getSiteStats()
      .then(res => { if (res.success) setStats(res.data); })
      .catch(() => {});

    getSessions(10)
      .then(res => { if (res.success) setSessions(res.data); })
      .catch(() => {})
      .finally(() => setSessLoading(false));
  }, []);

  async function handleToggle() {
    if (!settings || toggling) return;
    setToggling(true);
    const next = !settings.widget_enabled;
    try {
      await apiFetch('widget-settings', 'POST', { widget_enabled: next, greeting_message: settings.greeting_message });
      setSettings(prev => ({ ...prev, widget_enabled: next }));
    } catch {}
    finally { setToggling(false); }
  }

  const isEnabled = !!settings?.widget_enabled;

  const connectionItems = [
    {
      label: 'Server',
      ok:    !!(settings?.server_url),
      desc:  settings?.server_url ? 'Connected' : 'Not configured',
    },
    {
      label: 'Account Key',
      ok:    !!(settings?.account_key),
      desc:  settings?.account_key ? 'Set' : 'Missing',
    },
    {
      label: 'Site Key',
      ok:    !!(settings?.site_key),
      desc:  settings?.site_key ? 'Set' : 'Missing',
    },
  ];

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Dashboard</h1>
        <p className="text-muted small mb-0">Overview of your Sarah AI chat widget.</p>
      </div>

      <div className="row g-3">

        {/* Col 1 — Widget Image + Toggle */}
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div style={{ position: 'relative', overflow: 'hidden', borderRadius: '0.375rem 0.375rem 0 0', aspectRatio: '1 / 1' }}>
              <img
                src={sarahImg}
                alt="Sarah AI"
                style={{
                  width: '100%',
                  height: '100%',
                  objectFit: 'cover',
                  objectPosition: 'center top',
                  display: 'block',
                }}
              />
              <div style={{
                position: 'absolute',
                top: 0,
                left: 0,
                width: '100%',
                height: isEnabled ? '0%' : '100%',
                background: 'rgba(20, 20, 20, 0.88)',
                transition: 'height 0.6s cubic-bezier(0.4, 0, 0.2, 1)',
                pointerEvents: 'none',
              }} />
            </div>
            <div className="card-body">
              <div className="d-flex align-items-center justify-content-between">
                <div>
                  <div className="fw-semibold small">Chat Widget</div>
                  <div className={`small ${isEnabled ? 'text-success' : 'text-secondary'}`}>
                    {settings === null ? 'Loading…' : isEnabled ? 'Active' : 'Inactive'}
                  </div>
                </div>
                <div className="form-check form-switch mb-0">
                  <input
                    className="form-check-input"
                    type="checkbox"
                    role="switch"
                    checked={isEnabled}
                    onChange={handleToggle}
                    disabled={toggling || settings === null}
                    style={{ cursor: 'pointer', width: '2.5em', height: '1.25em' }}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Col 2 — Connection Status + Stats */}
        <div className="col-md-4 d-flex flex-column gap-3">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom">
              <h6 className="mb-0 fw-semibold small">Connection</h6>
            </div>
            <div className="card-body">
              <div className="d-flex flex-column gap-2">
                {connectionItems.map(item => (
                  <div key={item.label} className="d-flex align-items-center justify-content-between">
                    <span className="text-muted small">{item.label}</span>
                    <span className={`small fw-semibold ${item.ok ? 'text-success' : 'text-danger'}`}>
                      <i className={`bi ${item.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} me-1`} />
                      {item.desc}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom">
              <h6 className="mb-0 fw-semibold small">Quick Access</h6>
            </div>
            <div className="card-body p-0">
              {QUICK_LINKS.map((link, i) => (
                <div
                  key={link.view}
                  className={`d-flex align-items-center justify-content-between px-3 py-2 ${i < QUICK_LINKS.length - 1 ? 'border-bottom' : ''}`}
                >
                  <div>
                    <div className="fw-semibold small">{link.label}</div>
                    <div className="text-muted" style={{ fontSize: '0.75rem' }}>{link.desc}</div>
                  </div>
                  <button
                    className="btn btn-sm btn-primary orange-bg ms-3 text-nowrap"
                    style={{ fontSize: '0.75rem' }}
                    onClick={() => onNavigate?.(link.view)}
                  >
                    Go →
                  </button>
                </div>
              ))}
            </div>
          </div>

        </div>

        {/* Col 3 — Activity + Recent Sessions */}
        <div className="col-md-5">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom">
              <h6 className="mb-0 fw-semibold small">Activity</h6>
            </div>
            <div className="card-body border-bottom">
              <div className="d-flex flex-column gap-2">
                {[
                  { label: 'Total Sessions', value: stats.total_sessions },
                  { label: 'Total Messages', value: stats.total_messages },
                ].map(item => (
                  <div key={item.label} className="d-flex align-items-center justify-content-between">
                    <span className="text-muted small">{item.label}</span>
                    <span className="small fw-semibold text-secondary">
                      {item.value === null ? '—' : item.value.toLocaleString()}
                    </span>
                  </div>
                ))}
              </div>
            </div>
            <div className="px-3 pb-2 pt-1">
              <table className="table table-sm mb-0" style={{ fontSize: '0.8rem' }}>
                <thead className="table-light">
                  <tr>
                    <th>Visitor</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  {sessionsLoading ? (
                    <tr><td colSpan={4} className="text-muted text-center py-3">Loading…</td></tr>
                  ) : sessions.length === 0 ? (
                    <tr><td colSpan={4} className="text-muted text-center py-3">No sessions yet.</td></tr>
                  ) : (
                    sessions.map(s => {
                      const ip   = s.captured_data?.ip || null;
                      const name = s.visitor_name || null;
                      return (
                        <tr key={s.uuid}>
                          <td>
                            {name
                              ? <><span className="fw-semibold">{name}</span><br /><span className="text-muted font-monospace" style={{ fontSize: '0.72rem' }}>{ip || '—'}</span></>
                              : <span className="text-muted font-monospace" style={{ fontSize: '0.72rem' }}>{ip || '—'}</span>
                            }
                          </td>
                          <td className="text-muted" style={{ fontSize: '0.72rem' }}>
                            {s.visitor_email && <div>{s.visitor_email}</div>}
                            {s.visitor_phone && <div>{s.visitor_phone}</div>}
                            {!s.visitor_email && !s.visitor_phone && '—'}
                          </td>
                          <td>
                            <span className="badge bg-secondary-subtle text-secondary">{s.status}</span>
                          </td>
                          <td className="text-muted">
                            {s.created_at ? new Date(s.created_at).toLocaleDateString() : '—'}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </>
  );
}
