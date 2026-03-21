import React, { useState } from 'react';
import Sidebar from './components/Sidebar.jsx';
import Topbar from './components/Topbar.jsx';
import Dashboard from './pages/Dashboard.jsx';
import MenuManager from './pages/MenuManager.jsx';
import Log from './pages/Log.jsx';
import QuickQuestions from './pages/QuickQuestions.jsx';
import Settings from './pages/Settings.jsx';

const VIEWS = {
  dashboard: Dashboard,
  'menu-manager': MenuManager,
  'log': Log,
  'quick-questions': QuickQuestions,
  'settings': Settings,
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

  return (
    <div className="d-flex" style={{ minHeight: '100vh' }}>
      <Sidebar activeView={view} onNavigate={navigate} />
      <div className="main-wrap">
        <Topbar view={view} onNavigate={navigate} />
        <main className="main-content">
          <PageComponent />
        </main>
      </div>
    </div>
  );
}
