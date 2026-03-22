import React, { useState } from 'react';
import { LayoutProvider } from './context/LayoutContext.jsx';
import AdminLayout from './layouts/AdminLayout.jsx';
import Dashboard from './pages/Dashboard.jsx';
import MenuManager from './pages/MenuManager.jsx';
import Log from './pages/Log.jsx';
import Tenants from './pages/Tenants.jsx';
import TenantDetail from './pages/TenantDetail.jsx';

const VIEWS = {
  dashboard:       Dashboard,
  'menu-manager':  MenuManager,
  'log':           Log,
  'tenants':       Tenants,
  'tenant-detail': TenantDetail,
};

const LABELS = {
  dashboard:       'Dashboard',
  'menu-manager':  'Menu Manager',
  'log':           'System Log',
  'tenants':       'Tenants',
  'tenant-detail': 'Tenant Setup',
};

function parseHash() {
  const raw   = window.location.hash.replace(/^#\//, '');
  const parts = raw.split('/');
  return { view: parts[0] || 'dashboard', param: parts[1] || null };
}

export default function App() {
  const [route, setRoute] = useState(parseHash);

  function navigate(view, param = null) {
    const hash = param ? `${view}/${param}` : view;
    setRoute({ view, param });
    window.location.hash = '/' + hash;
  }

  const { view, param } = route;
  const PageComponent   = VIEWS[view] ?? Dashboard;
  const pageTitle       = LABELS[view] ?? view.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

  return (
    <LayoutProvider>
      <AdminLayout title={pageTitle} onNavigate={navigate} activeView={view}>
        <PageComponent param={param} onNavigate={navigate} />
      </AdminLayout>
    </LayoutProvider>
  );
}
