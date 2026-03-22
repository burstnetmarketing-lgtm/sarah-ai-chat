import React, { useState } from 'react';
import Sidebar from './components/Sidebar.jsx';
import Topbar from './components/Topbar.jsx';
import Dashboard from './pages/Dashboard.jsx';
import MenuManager from './pages/MenuManager.jsx';
import Log from './pages/Log.jsx';

const VIEWS = {
  dashboard:      Dashboard,
  'menu-manager': MenuManager,
  'log':          Log,
};

const LABELS = {
  dashboard:      'Dashboard',
  'menu-manager': 'Menu Manager',
  'log':          'System Log',
};

export default function App() {
  const [view, setView] = useState(() => {
    return window.location.hash.replace('#/', '') || 'dashboard';
  });

  function navigate(v) {
    setView(v);
    window.location.hash = '/' + v;
  }

  const PageComponent = VIEWS[view] ?? Dashboard;
  const pageTitle     = LABELS[view] ?? view.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

  return (
    <div className="wrapper">
      <Sidebar activeView={view} onNavigate={navigate} />
      <Topbar title={pageTitle} onNavigate={navigate} />
      <div className="content-page">
        <div className="content">
          <PageComponent />
        </div>
        <footer className="admin-footer">
          &copy; {new Date().getFullYear()} Sarah AI Server
        </footer>
      </div>
    </div>
  );
}
