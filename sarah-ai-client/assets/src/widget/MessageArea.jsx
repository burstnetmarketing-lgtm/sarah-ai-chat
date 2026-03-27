import React, { useEffect, useRef } from 'react';
import TypingIndicator from './TypingIndicator.jsx';
import ContactCard from './ContactCard.jsx';

const customQuickQuestions = (window.SarahAiWidget?.quickQuestions || []).map(q => q.question);
const welcomeMessage       = window.SarahAiWidget?.settings?.welcomeMessage || 'How can I help you today?';

// ─── Language seed options ────────────────────────────────────────────────────
// Shown in the empty state when the site owner has not configured custom
// quick questions. Clicking one triggers handleLanguageSelect in ChatWindow,
// which shows the label as the user bubble and sends the instruction message
// to the API so the AI responds in the selected language from that point on.
// Languages are loaded from the DB via wp_localize_script (SarahAiWidget.languages).
// Each row: { code, label, message }
export const LANGUAGE_OPTIONS = (window.SarahAiWidget?.languages || []).map(row => ({
  label:    row.label,
  message:  row.message,
  language: row.code,
}));

export default function MessageArea({ messages, isTyping, awaitingLang, onQuickQuestion, onRetry }) {
  const bottomRef = useRef(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  const showLangPicker = awaitingLang;

  if (messages.length === 0) {
    return (
      <div className="sac-messages">
        <div className="sac-messages-empty">
          <div className="sac-welcome">
            <div className="sac-welcome-emoji">👋</div>
            <p className="sac-welcome-title">Hi there!</p>
            <p className="sac-welcome-sub">{welcomeMessage}</p>
            {showLangPicker && (
              <p className="sac-lang-hint">What language would you like to chat in?</p>
            )}
          </div>

          {customQuickQuestions.length > 0 && (
            <div className="sac-quick-questions">
              {customQuickQuestions.map((q, i) => (
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
        <div
          key={msg.id}
          className={`sac-bubble sac-bubble-${msg.type}${msg.isError ? ' sac-bubble-error' : ''}`}
          dir={msg.dir || 'ltr'}
        >
          {msg.type === 'ai'
            ? <div className="sac-bubble-html" dangerouslySetInnerHTML={{ __html: msg.text }} />
            : msg.text}
          {msg.cardData?.fields && <ContactCard fields={msg.cardData.fields} />}
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
