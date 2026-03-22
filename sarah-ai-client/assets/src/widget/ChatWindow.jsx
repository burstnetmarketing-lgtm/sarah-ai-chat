import React, { useState, useCallback, useEffect } from 'react';
import Header from './Header.jsx';
import MessageArea from './MessageArea.jsx';
import InputBox from './InputBox.jsx';
import { sendChatMessage, fetchChatHistory } from './chatApi.js';

let _id = 0;
function nextId() { return ++_id; }

// ─── Structured response parser ──────────────────────────────────────────────
// The AI may append a <sarah_card>...</sarah_card> block containing structured
// contact data. This function strips the tag from display text and returns the
// parsed card data separately so MessageArea can render a formatted ContactCard.
//
// TODO: add policy/access-control filtering before formatter
// TODO: support tenant-specific business data source

const SARAH_CARD_RE = /<sarah_card>([\s\S]*?)<\/sarah_card>/i;

function parseAiResponse(rawText) {
  const match = rawText.match(SARAH_CARD_RE);
  if (!match) return { text: rawText, cardData: null };

  const text = rawText.replace(SARAH_CARD_RE, '').trim();
  try {
    const cardData = JSON.parse(match[1]);
    // Only accept the expected shape
    if (cardData && Array.isArray(cardData.fields)) {
      return { text, cardData };
    }
  } catch { /* malformed JSON — discard the tag, show full text */ }
  return { text: rawText, cardData: null };
}

// ─── localStorage helpers ────────────────────────────────────────────────────
// Key is scoped to the site_key so multiple sites on the same domain don't collide.

function storageKey() {
  const siteKey = window.SarahAiWidget?.connection?.site_key || 'default';
  return `sarah_ai_session_${siteKey}`;
}

function loadStoredSession() {
  try { return localStorage.getItem(storageKey()) || null; }
  catch { return null; }
}

function saveStoredSession(uuid) {
  try { localStorage.setItem(storageKey(), uuid); } catch { /* storage blocked */ }
}

function clearStoredSession() {
  try { localStorage.removeItem(storageKey()); } catch { /* storage blocked */ }
}

// ─── Config helpers ──────────────────────────────────────────────────────────

function getGreeting() {
  return (window.SarahAiWidget?.connection?.greeting_message || '').trim();
}

function getLead() {
  const lead = window.SarahAiWidget?.connection?.lead;
  return lead && typeof lead === 'object' ? lead : null;
}

function greetingMessage() {
  const text = getGreeting();
  return text ? [{ id: nextId(), type: 'ai', text }] : [];
}

// ─── ChatWindow ──────────────────────────────────────────────────────────────

export default function ChatWindow({ onClose }) {
  const [messages, setMessages]     = useState([]);
  const [isTyping, setIsTyping]     = useState(false);
  const [sessionUuid, setSessionUuid] = useState(null);
  const [lastFailed, setLastFailed] = useState(null); // text of last failed send
  const [historyLoading, setHistoryLoading] = useState(false);

  // ── On mount: restore session + history, or show greeting ────────────────
  useEffect(() => {
    const stored = loadStoredSession();

    if (!stored) {
      // No previous session — show greeting immediately
      setMessages(greetingMessage());
      return;
    }

    // Attempt to restore history
    setSessionUuid(stored);
    setHistoryLoading(true);

    fetchChatHistory(stored)
      .then(history => {
        if (history === null) {
          // Session no longer valid on server — discard and start fresh
          clearStoredSession();
          setSessionUuid(null);
          setMessages(greetingMessage());
        } else if (history.length === 0) {
          // Valid session but no messages yet — show greeting
          setMessages(greetingMessage());
        } else {
          // Restore conversation — no greeting (user already saw it)
          setMessages(history.map(msg => {
            if (msg.role !== 'customer') {
              const { text, cardData } = parseAiResponse(msg.content);
              return { id: nextId(), type: 'ai', text, cardData };
            }
            return { id: nextId(), type: 'user', text: msg.content };
          }));
        }
      })
      .catch(() => {
        // Network failure — keep session, show greeting, let user retry from UI
        setMessages(greetingMessage());
      })
      .finally(() => setHistoryLoading(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // ── Send message ─────────────────────────────────────────────────────────
  const sendMessage = useCallback((text) => {
    const trimmed = text.trim();
    if (!trimmed || isTyping) return;

    setLastFailed(null);
    setMessages(prev => [...prev, { id: nextId(), type: 'user', text: trimmed }]);
    setIsTyping(true);

    sendChatMessage(trimmed, sessionUuid, getLead())
      .then(data => {
        // Task 1: persist session UUID on first reply
        if (data.session_uuid) {
          if (!sessionUuid) {
            setSessionUuid(data.session_uuid);
            saveStoredSession(data.session_uuid);
          }
        }
        const { text: aiText, cardData } = parseAiResponse(data.message);
        setMessages(prev => [...prev, { id: nextId(), type: 'ai', text: aiText, cardData }]);
      })
      .catch(err => {
        const errorText = err.message?.includes('not configured')
          ? 'Chat is not configured yet.'
          : 'Unable to connect. Please try again.';
        // Task 4: mark message as error + store retry text
        setLastFailed(trimmed);
        setMessages(prev => [...prev, {
          id:        nextId(),
          type:      'ai',
          text:      errorText,
          isError:   true,
          retryText: trimmed,
        }]);
      })
      .finally(() => setIsTyping(false));
  }, [isTyping, sessionUuid]);

  // ── Task 4: retry last failed message ────────────────────────────────────
  const handleRetry = useCallback((text) => {
    // Remove the error bubble then resend
    setMessages(prev => prev.filter(m => !(m.isError && m.retryText === text)));
    setLastFailed(null);
    sendMessage(text);
  }, [sendMessage]);

  // ── Task 6: reset chat ────────────────────────────────────────────────────
  const handleReset = useCallback(() => {
    clearStoredSession();
    setSessionUuid(null);
    setLastFailed(null);
    setMessages(greetingMessage());
  }, []);

  return (
    <div className="sac-window" role="dialog" aria-label="Chat window">
      <Header onClose={onClose} onReset={handleReset} />
      <MessageArea
        messages={messages}
        isTyping={isTyping || historyLoading}
        onQuickQuestion={sendMessage}
        onRetry={handleRetry}
      />
      <InputBox onSend={sendMessage} disabled={isTyping || historyLoading} />
    </div>
  );
}
