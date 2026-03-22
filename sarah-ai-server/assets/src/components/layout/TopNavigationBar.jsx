import React, { useState, useRef, useEffect } from 'react';
import { Icon } from '@iconify/react';
import { useLayoutContext } from '../../context/LayoutContext.jsx';
import useViewPort from '../../hooks/useViewPort.js';

function LeftSidebarToggle() {
  const { menu: { size }, changeMenu: { size: changeMenuSize }, toggleBackdrop } = useLayoutContext();
  const { width } = useViewPort();
  const firstRender = useRef(true);

  useEffect(() => {
    if (firstRender.current) { firstRender.current = false; return; }
    if (width <= 768)        changeMenuSize('full');
    else if (width <= 1140)  changeMenuSize('condensed');
    else                     changeMenuSize('default');
  }, [width]);

  function handleClick() {
    if (size === 'full')      toggleBackdrop();
    else if (size === 'condensed') changeMenuSize('default');
    else                     changeMenuSize('condensed');
  }

  return (
    <button onClick={handleClick} className="button-toggle-menu">
      <Icon icon="mdi:menu" />
    </button>
  );
}

function ProfileDropdown({ onNavigate }) {
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

  function go(viewKey) { setOpen(false); onNavigate(viewKey); }

  return (
    <li ref={ref} className="position-relative" style={{ listStyle: 'none' }}>
      <a className="nav-link nav-user arrow-none" role="button" style={{ cursor: 'pointer' }}
         onClick={() => setOpen(o => !o)}>
        <span className="account-user-avatar">
          <div style={{
            width: 32, height: 32, borderRadius: '50%',
            background: '#1a3460', color: '#f5c518',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '0.75rem', fontWeight: 700, flexShrink: 0,
          }}>
            {initials}
          </div>
        </span>
        <span className="d-lg-block d-none">
          <h5 className="my-0 fw-normal">
            {userName}&nbsp;
            <Icon icon="ri:arrow-down-s-line" style={{ fontSize: 22, verticalAlign: 'middle' }} />
          </h5>
        </span>
      </a>

      {open && (
        <div className="dropdown-menu dropdown-menu-end dropdown-menu-animated profile-dropdown show"
             style={{ position: 'absolute', right: 0, top: '110%', zIndex: 1050, display: 'block' }}>
          {canManageMenus && (
            <button className="dropdown-item" onClick={() => go('menu-manager')}>
              <Icon icon="mdi:format-list-bulleted" className="me-1 align-middle" /> Menu Manager
            </button>
          )}
          <button className="dropdown-item" onClick={() => go('log')}>
            <Icon icon="mdi:text-box-outline" className="me-1 align-middle" /> System Log
          </button>
          <div className="dropdown-divider" />
          {adminUrl && (
            <a className="dropdown-item" href={adminUrl}>
              <Icon icon="mdi:wordpress" className="me-1 align-middle" /> WordPress Admin
            </a>
          )}
          <a className="dropdown-item text-danger" href={logoutUrl}>
            <Icon icon="ri:logout-circle-r-line" className="me-1 align-middle" /> Logout
          </a>
        </div>
      )}
    </li>
  );
}

export default function TopNavigationBar({ title, onNavigate }) {
  return (
    <div className="navbar-custom">
      <div className="topbar container-fluid">
        <div className="d-flex align-items-center gap-1">
          <div className="logo-topbar">
            <span style={{ fontWeight: 700, fontSize: '1.1rem' }}>Sarah AI</span>
          </div>
          <LeftSidebarToggle />
          {title && <h4 className="page-title d-none d-sm-block">{title}</h4>}
        </div>
        <ul className="topbar-menu d-flex align-items-center gap-3 mb-0 ps-0">
          <ProfileDropdown onNavigate={onNavigate} />
        </ul>
      </div>
    </div>
  );
}
