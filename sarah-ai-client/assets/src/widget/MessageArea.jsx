import React from 'react';

export default function MessageArea() {
  return (
    <div className="sac-messages">
      <div className="sac-messages-empty">
        <div className="sac-empty-state">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" opacity="0.4">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <p>How can I help you today?</p>
        </div>
      </div>
    </div>
  );
}
