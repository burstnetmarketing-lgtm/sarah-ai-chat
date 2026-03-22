import React, { useEffect, useRef } from 'react';
import TypingIndicator from './TypingIndicator.jsx';

const quickQuestions  = (window.SarahAiWidget?.quickQuestions || []).map(q => q.question);
const welcomeMessage  = window.SarahAiWidget?.settings?.welcomeMessage || 'How can I help you today?';

export default function MessageArea({ messages, isTyping, onQuickQuestion, onRetry }) {
  const bottomRef = useRef(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  if (messages.length === 0) {
    return (
      <div className="sac-messages">
        <div className="sac-messages-empty">
          <div className="sac-welcome">
            <div className="sac-welcome-emoji">👋</div>
            <p className="sac-welcome-title">Hi there!</p>
            <p className="sac-welcome-sub">{welcomeMessage}</p>
          </div>
          {quickQuestions.length > 0 && (
            <div className="sac-quick-questions">
              {quickQuestions.map((q, i) => (
                <button key={i} className="sac-quick-btn" onClick={() => onQuickQuestion(q)}>
                  {q}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="sac-messages">
      {messages.map(msg => (
        <div key={msg.id} className={`sac-bubble sac-bubble-${msg.type}${msg.isError ? ' sac-bubble-error' : ''}`}>
          {msg.text}
          {msg.isError && msg.retryText && onRetry && (
            <button
              className="sac-retry-btn"
              onClick={() => onRetry(msg.retryText)}
              aria-label="Retry sending message"
            >
              ↺ Try again
            </button>
          )}
        </div>
      ))}
      {isTyping && <TypingIndicator />}
      <div ref={bottomRef} />
    </div>
  );
}
