import React, { useState } from 'react';

export default function InputBox({ onSend, disabled }) {
  const [value, setValue] = useState('');

  function handleSend() {
    if (!value.trim() || disabled) return;
    onSend(value);
    setValue('');
  }

  function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }

  return (
    <div className="sac-input-area">
      <input
        className="sac-input"
        type="text"
        placeholder="Type a message..."
        value={value}
        onChange={e => setValue(e.target.value)}
        onKeyDown={handleKeyDown}
        disabled={disabled}
        aria-label="Message input"
      />
      <button
        className="sac-send"
        onClick={handleSend}
        disabled={disabled || !value.trim()}
        aria-label="Send message"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
        </svg>
      </button>
    </div>
  );
}
