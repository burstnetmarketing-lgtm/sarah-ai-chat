import React, { useState, useRef, useEffect } from 'react';

export default function Topbar({ title, onNavigate }) {
  const { adminUrl, logoutUrl, userName, initials, canManageMenus } =
    window.SarahAiServerConfig || {};

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
    <header className="navbar-custom">
      <span className="topbar-title">{title}</span>

      <div className="d-flex align-items-center gap-3">
        <a href="/" className="topbar-action" title="Back to WordPress">
          <i className="bi bi-arrow-left-circle"></i>
        </a>

        <div className="topbar-divider" />

        <div className="position-relative" ref={ref}>
          <button
            className="btn d-flex align-items-center gap-2 p-0 border-0 bg-transparent"
            onClick={() => setOpen(o => !o)}
          >
            <span className="text-muted small">{userName}</span>
            <div className="user-avatar">{initials}</div>
          </button>

          {open && (
            <ul
              className="dropdown-menu dropdown-menu-end show position-absolute"
              style={{ top: '110%', right: 0, minWidth: '185px', zIndex: 1050 }}
            >
              {canManageMenus && (
                <li>
                  <button className="dropdown-item d-flex align-items-center gap-2"
                          onClick={() => go('menu-manager')}>
                    <i className="bi bi-list-nested"></i> Menu Manager
                  </button>
                </li>
              )}
              <li>
                <button className="dropdown-item d-flex align-items-center gap-2"
                        onClick={() => go('log')}>
                  <i className="bi bi-journal-text"></i> System Log
                </button>
              </li>
              <li><hr className="dropdown-divider" /></li>
              {adminUrl && (
                <li>
                  <a className="dropdown-item d-flex align-items-center gap-2"
                     href={adminUrl}>
                    <i className="bi bi-speedometer2"></i> Back to Admin
                  </a>
                </li>
              )}
              <li>
                <a className="dropdown-item d-flex align-items-center gap-2 text-danger"
                   href={logoutUrl}>
                  <i className="bi bi-box-arrow-right"></i> Log out
                </a>
              </li>
            </ul>
          )}
        </div>
      </div>
    </header>
  );
}
