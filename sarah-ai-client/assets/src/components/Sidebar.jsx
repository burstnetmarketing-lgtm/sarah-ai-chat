import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

export default function Sidebar({ activeView, onNavigate }) {
  const [parents, setParents] = useState([]);

  useEffect(() => {
    apiFetch('menu-items')
      .then(res => { if (res.success) setParents(res.data.parents); })
      .catch(() => {});
  }, [activeView]);

  return (
    <aside className="sidebar">
      <div className="sidebar-brand">Project <span>Name</span></div>
      <nav className="sidebar-nav">
        <ul className="nav flex-column gap-1">
          {parents.map(parent => (
            <SidebarItem key={parent.item_key} parent={parent} activeView={activeView} onNavigate={onNavigate} />
          ))}
        </ul>
      </nav>
    </aside>
  );
}

function SidebarItem({ parent, activeView, onNavigate }) {
  const children = parent.children || [];
  const isChildActive = children.some(c => c.view_key === activeView);
  const collapseId = `sidebar-${parent.item_key}`;

  if (children.length === 0) {
    return (
      <li className="nav-item">
        <a href="#" className={`nav-link ${activeView === parent.view_key ? 'active' : ''}`}
           onClick={e => { e.preventDefault(); onNavigate(parent.view_key); }}>
          <i className="bi bi-circle"></i> {parent.label}
        </a>
      </li>
    );
  }

  return (
    <li className="nav-item">
      <a href={`#${collapseId}`}
         className={`nav-link ${isChildActive ? '' : 'collapsed'}`}
         data-bs-toggle="collapse"
         aria-expanded={isChildActive ? 'true' : 'false'}>
        <i className="bi bi-circle"></i>
        {parent.label}
        <i className="bi bi-chevron-down sidebar-chevron ms-auto small"></i>
      </a>
      <div className={`collapse ${isChildActive ? 'show' : ''}`} id={collapseId}>
        <ul className="nav flex-column sidebar-children">
          {children.map(child => (
            <li key={child.item_key} className="nav-item">
              <a href="#"
                 className={`nav-link sidebar-child ${activeView === child.view_key ? 'active' : ''}`}
                 onClick={e => { e.preventDefault(); onNavigate(child.view_key); }}>
                <i className="bi bi-chevron-right small"></i> {child.label}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </li>
  );
}
