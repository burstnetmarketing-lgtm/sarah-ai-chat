import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

const ICONS = {
  dashboard:      'bi-speedometer2',
  'menu-manager': 'bi-list-nested',
  'log':          'bi-journal-text',
  settings:       'bi-gear',
};

export default function Sidebar({ activeView, onNavigate }) {
  const [parents, setParents] = useState([]);

  useEffect(() => {
    apiFetch('menu-items')
      .then(res => { if (res.success) setParents(res.data.parents); })
      .catch(() => {});
  }, []);

  return (
    <aside className="leftside-menu">

      {/* Logo */}
      <div className="sidebar-logo">
        <div className="logo-text">
          Sarah <span className="logo-accent">AI</span>
        </div>
      </div>

      {/* Navigation */}
      <div className="sidebar-scroll">
        <ul className="side-nav">
          {parents.map(parent => (
            <SidebarItem
              key={parent.item_key}
              parent={parent}
              activeView={activeView}
              onNavigate={onNavigate}
            />
          ))}
        </ul>
      </div>

    </aside>
  );
}

function SidebarItem({ parent, activeView, onNavigate }) {
  const children      = parent.children || [];
  const isChildActive = children.some(c => c.view_key === activeView);
  const [open, setOpen] = useState(isChildActive);
  const icon = ICONS[parent.view_key] || 'bi-circle';

  if (children.length === 0) {
    return (
      <li className="side-nav-item">
        <button
          className={`side-nav-link ${activeView === parent.view_key ? 'active' : ''}`}
          onClick={() => onNavigate(parent.view_key)}
        >
          <i className={`bi ${icon}`}></i>
          {parent.label}
        </button>
      </li>
    );
  }

  return (
    <li className="side-nav-item">
      <button
        className={`side-nav-link ${open ? '' : 'collapsed'}`}
        onClick={() => setOpen(o => !o)}
      >
        <i className={`bi ${icon}`}></i>
        {parent.label}
        <i className="bi bi-chevron-down nav-chevron"></i>
      </button>
      {open && (
        <ul className="side-nav-second-level">
          {children.map(child => (
            <li key={child.item_key} className="side-nav-item">
              <button
                className={`side-nav-link ${activeView === child.view_key ? 'active' : ''}`}
                onClick={() => onNavigate(child.view_key)}
              >
                {child.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </li>
  );
}
