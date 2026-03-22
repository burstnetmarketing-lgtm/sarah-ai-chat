import React from 'react';
import TopNavigationBar from '../components/layout/TopNavigationBar.jsx';
import VerticalNavigationBar from '../components/layout/VerticalNavigationBar.jsx';
import Footer from '../components/layout/Footer.jsx';

const AdminLayout = ({ children, title, onNavigate, activeView }) => {
  return (
    <div className="wrapper">
      <TopNavigationBar title={title} onNavigate={onNavigate} />
      <VerticalNavigationBar activeView={activeView} onNavigate={onNavigate} />
      <div className="content-page">
        <div className="content">
          <div className="container-fluid">
            {children}
          </div>
          <Footer />
        </div>
      </div>
    </div>
  );
};

export default AdminLayout;
