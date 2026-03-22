import React, { useState, useEffect } from 'react';
import SimpleBar from 'simplebar-react';
import 'simplebar-react/dist/simplebar.min.css';
import { Collapse } from 'react-bootstrap';
import { Icon } from '@iconify/react';
import clsx from 'clsx';
import { apiFetch } from '../../api/client.js';

const ICONS = {
  dashboard:      'mdi:view-dashboard-outline',
  'menu-manager': 'mdi:format-list-bulleted',
  'log':          'mdi:text-box-outline',
  settings:       'mdi:cog-outline',
};

function LogoBox() {
  return (
    <a className="logo logo-light" href="#/dashboard" style={{ textDecoration: 'none' }}>
      <span className="logo-lg">
        <span style={{ fontWeight: 700, fontSize: '1.25rem', color: '#fff', letterSpacing: '-0.01em' }}>
          Sarah <span style={{ color: '#f5c518' }}>AI</span>
        </span>
      </span>
      <span className="logo-sm">
        <span style={{ fontWeight: 700, fontSize: '1.1rem', color: '#f5c518' }}>S</span>
      </span>
    </a>
  );
}

function MenuItem({ item, activeView, onNavigate }) {
  const isActive = activeView === item.view_key;
  const icon = ICONS[item.view_key] || 'mdi:circle-outline';
  return (
    <li className={clsx('side-nav-item', isActive && 'menuitem-active')}>
      <button
        className={clsx('side-nav-link', isActive && 'active')}
        onClick={() => onNavigate(item.view_key)}
        style={{ border: 'none', width: '100%', textAlign: 'left', background: 'none', cursor: 'pointer' }}
      >
        <Icon icon={icon} />
        <span className="ms-2">{item.label}</span>
      </button>
    </li>
  );
}

function MenuItemWithChildren({ item, activeView, onNavigate }) {
  const children      = item.children || [];
  const isChildActive = children.some(c => c.view_key === activeView);
  const [open, setOpen] = useState(isChildActive);
  const icon = ICONS[item.view_key] || 'mdi:circle-outline';

  return (
    <li className={clsx('side-nav-item', (open || isChildActive) && 'menuitem-active')}>
      <div
        className="side-nav-link"
        onClick={() => setOpen(o => !o)}
        role="button"
        aria-expanded={open}
        style={{ cursor: 'pointer', userSelect: 'none' }}
      >
        <Icon icon={icon} />
        <span className="ms-2">{item.label}</span>
        <span className="menu-arrow">
          <Icon icon="ri:arrow-drop-right-line" width={24} height={24} />
        </span>
      </div>
      <Collapse in={open}>
        <div>
          <ul className="side-nav-second-level">
            {children.map(child => (
              <li key={child.item_key}
                  className={clsx('side-nav-item', activeView === child.view_key && 'menuitem-active')}>
                <button
                  className={clsx('side-nav-link', activeView === child.view_key && 'active')}
                  onClick={() => onNavigate(child.view_key)}
                  style={{ border: 'none', width: '100%', textAlign: 'left', background: 'none', cursor: 'pointer' }}
                >
                  <span className="ms-2 child-label">{child.label}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      </Collapse>
    </li>
  );
}

export default function VerticalNavigationBar({ activeView, onNavigate }) {
  const [parents, setParents] = useState([]);

  useEffect(() => {
    apiFetch('menu-items')
      .then(res => { if (res.success) setParents(res.data.parents); })
      .catch(() => {});
  }, []);

  return (
    <div className="leftside-menu" id="leftside-menu-container">
      <LogoBox />
      <SimpleBar style={{ height: 'calc(100% - var(--bs-topbar-height, 75px))' }}>
        <ul className="side-nav">
          {parents.map(item => (
            item.children?.length > 0
              ? <MenuItemWithChildren key={item.item_key} item={item} activeView={activeView} onNavigate={onNavigate} />
              : <MenuItem            key={item.item_key} item={item} activeView={activeView} onNavigate={onNavigate} />
          ))}
        </ul>
      </SimpleBar>
    </div>
  );
}
