import React from 'react';

export default function Dashboard() {
  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Dashboard</h1>
        <p className="text-muted small mb-0">Welcome to Project Name.</p>
      </div>
      <div className="card border-0 shadow-sm">
        <div className="card-body text-center py-5">
          <i className="bi bi-box-seam text-primary icon-xl"></i>
          <p className="fw-semibold text-dark mt-3 mb-1">Plugin boilerplate ready.</p>
          <p className="text-muted small mb-0">React app is running.</p>
        </div>
      </div>
    </>
  );
}
