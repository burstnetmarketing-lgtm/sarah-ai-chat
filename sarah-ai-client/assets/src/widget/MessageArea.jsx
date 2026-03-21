import React from 'react';

export default function MessageArea() {
  return (
    <div className="sac-messages">
      <div className="sac-messages-empty">
        <div className="sac-welcome">
          <div className="sac-welcome-emoji">👋</div>
          <p className="sac-welcome-title">Hi there!</p>
          <p className="sac-welcome-sub">How can I help you today?</p>
        </div>
      </div>
    </div>
  );
}
