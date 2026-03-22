import React, { useState } from 'react';
import { LayoutProvider } from './context/LayoutContext.jsx';
import AdminLayout from './layouts/AdminLayout.jsx';
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
    <LayoutProvider>
      <AdminLayout title={pageTitle} onNavigate={navigate} activeView={view}>
        <PageComponent />
      </AdminLayout>
    </LayoutProvider>
  );
}
