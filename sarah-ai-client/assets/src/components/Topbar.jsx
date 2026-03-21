import React, { useState, useRef, useEffect } from 'react';

const LABELS = {
  dashboard: 'Dashboard',
  'menu-manager': 'Menu Manager',
};

export default function Topbar({ view, onNavigate }) {
  const { adminUrl, userName, initials, canManageMenus } = window.SarahAiClientConfig || {};
  const title = LABELS[view] ?? view.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  const [open, setOpen] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    function handler(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  function go(viewKey) {
    setOpen(false);
    onNavigate(viewKey);
  }

  return (
    <header className="topbar">
      <span className="topbar-title">{title}</span>
      <div className="d-flex align-items-center gap-3 ms-auto">
        <a href={adminUrl} className="topbar-action" title="Back to WordPress">
          <i className="bi bi-arrow-left-circle"></i>
        </a>
        <div className="topbar-divider"></div>
        <div className="position-relative" ref={ref}>
          <button className="btn d-flex align-items-center gap-2 p-0 border-0 bg-transparent"
                  onClick={() => setOpen(o => !o)}>
            <span className="text-muted small">{userName}</span>
            <div className="user-avatar">{initials}</div>
          </button>
          {open && (
            <ul className="dropdown-menu dropdown-menu-end show position-absolute" style={{ top: '110%', right: 0, minWidth: '170px' }}>
              <li>
                <button className="dropdown-item d-flex align-items-center gap-2"
                        onClick={() => go('menu-manager')}
                        disabled={!canManageMenus}>
                  <i className="bi bi-list-nested"></i> Menu Manager
                </button>
              </li>
              <li>
                <button className="dropdown-item d-flex align-items-center gap-2"
                        onClick={() => go('log')}
                        disabled={!canManageMenus}>
                  <i className="bi bi-journal-text"></i> Log
                </button>
              </li>
            </ul>
          )}
        </div>
      </div>
    </header>
  );
}
