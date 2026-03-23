import React, { useState } from 'react';
import Sidebar from './components/Sidebar.jsx';
import Topbar from './components/Topbar.jsx';
import Dashboard from './pages/Dashboard.jsx';
import MenuManager from './pages/MenuManager.jsx';
import Log from './pages/Log.jsx';
import QuickQuestions from './pages/QuickQuestions.jsx';
import AppearanceSettings from './pages/AppearanceSettings.jsx';
import Settings from './pages/Settings.jsx';
import KnowledgeBase from './pages/KnowledgeBase.jsx';
import QuickSetup from './pages/QuickSetup.jsx';
import PublicApi from './pages/PublicApi.jsx';

const VIEWS = {
  dashboard:        Dashboard,
  'menu-manager':   MenuManager,
  'log':            Log,
  'quick-questions': QuickQuestions,
  'appearance':     AppearanceSettings,
  'settings':       Settings,
  'knowledge-base': KnowledgeBase,
  'public-api':     PublicApi,
};

export default function App() {
  const [view, setView] = useState(() => {
    return window.location.hash.replace('#/', '') || 'dashboard';
  });

  // Show Quick Setup wizard if plugin is not yet connected to the server
  if (!window.SarahAiClientConfig?.isConfigured) {
    return <QuickSetup />;
  }

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
