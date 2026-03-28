import React, { useState, useCallback, useEffect, useRef } from 'react';
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
const SARAH_META_RE = /<sarah_meta>([\s\S]*?)<\/sarah_meta>/i;

// ─── RTL / LTR direction detection ───────────────────────────────────────────
// Strips HTML tags and checks whether the first meaningful characters are
// in an RTL Unicode range (Arabic, Persian, Hebrew, etc.).
// Used to set dir="rtl/ltr" on each chat bubble individually.
const RTL_RE = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;

function detectDir(text) {
  const plain = text.replace(/<[^>]+>/g, '').trimStart();
  return RTL_RE.test(plain.slice(0, 120)) ? 'rtl' : 'ltr';
}

// ─── Markdown → HTML safety net ──────────────────────────────────────────────
// The AI is instructed to respond in HTML, but may occasionally slip into
// Markdown (especially in longer conversations). This converts common Markdown
// patterns to HTML as a client-side fallback. Only runs when the response
// contains no HTML tags (pure Markdown output).
function markdownToHtml(text) {
  // If the text already contains HTML tags, trust it and return as-is
  if (/<[a-z][\s\S]*?>/i.test(text)) return text;

  return text
    // Headings: ### → <h4>, ## → <h3>
    .replace(/^###\s+(.+)$/gm, '<h4>$1</h4>')
    .replace(/^##\s+(.+)$/gm, '<h3>$1</h3>')
    .replace(/^#\s+(.+)$/gm, '<h3>$1</h3>')
    // Bold+italic: ***text*** or ___text___
    .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
    // Bold: **text** or __text__
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/__(.+?)__/g, '<strong>$1</strong>')
    // Italic: *text* or _text_
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/_(.+?)_/g, '<em>$1</em>')
    // Unordered lists: lines starting with - or *
    .replace(/^(?:[*-])\s+(.+)$/gm, '<li>$1</li>')
    // Ordered lists: lines starting with 1. 2. etc
    .replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>')
    // Wrap consecutive <li> blocks in <ul>
    .replace(/(<li>.*<\/li>\n?)+/gs, match => '<ul>' + match + '</ul>')
    // Paragraphs: blank-line-separated blocks not already wrapped in a tag
    .split(/\n{2,}/)
    .map(block => {
      block = block.trim();
      if (!block) return '';
      if (/^<(h[1-6]|ul|ol|li|blockquote)/i.test(block)) return block;
      return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
    })
    .filter(Boolean)
    .join('\n');
}

function parseAiResponse(rawText) {
  // ── 1. Extract <sarah_meta> for lang/dir (strip from display text) ─────
  let working = rawText;
  let dir = null;
  const metaMatch = working.match(SARAH_META_RE);
  if (metaMatch) {
    working = working.replace(SARAH_META_RE, '').trim();
  }

  // ── 2. Extract <sarah_card> for contact card ───────────────────────────
  const cardMatch = working.match(SARAH_CARD_RE);
  let cardData = null;
  if (cardMatch) {
    try {
      const parsed = JSON.parse(cardMatch[1]);
      if (parsed && Array.isArray(parsed.fields)) cardData = parsed;
    } catch { /* malformed */ }
    working = working.replace(SARAH_CARD_RE, '').trim();
  }

  // ── 3. Markdown → HTML safety net ─────────────────────────────────────
  const text = markdownToHtml(working);

  // ── 4. Direction: from AI meta tag, or fallback to char detection ──────
  if (dir === null) dir = detectDir(text);

  return { text, cardData, dir };
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
  const [messages, setMessages]       = useState([]);
  const [isTyping, setIsTyping]       = useState(false);
  const [sessionUuid, setSessionUuid] = useState(null);
  const [lastFailed, setLastFailed]   = useState(null);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [language, setLanguage]       = useState(null);
  const [sessionDir, setSessionDir]   = useState('ltr');
  const windowRef = useRef(null);

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
              const { text, cardData, dir } = parseAiResponse(msg.content);
              return { id: nextId(), type: 'ai', text, cardData, dir };
            }
            return { id: nextId(), type: 'user', text: msg.content, dir: detectDir(msg.content) };
          }));
        }
      })
      .catch(() => {
        // Network failure — keep session, show greeting, let user retry from UI
        setMessages(greetingMessage());
      })
      .finally(() => setHistoryLoading(false));
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // ── Mobile: keep root fullscreen above keyboard using Visual Viewport API ─
  useEffect(() => {
    const vv   = window.visualViewport;
    const root = document.getElementById('sarah-chat-root');
    if (!vv || !root) return;

    const onResize = () => {
      if (window.innerWidth > 768) return;
      root.style.top    = vv.offsetTop  + 'px';
      root.style.left   = vv.offsetLeft + 'px';
      root.style.width  = vv.width      + 'px';
      root.style.height = vv.height     + 'px';
    };

    vv.addEventListener('resize', onResize);
    vv.addEventListener('scroll', onResize);
    onResize();

    return () => {
      vv.removeEventListener('resize', onResize);
      vv.removeEventListener('scroll', onResize);
    };
  }, []);

  // ── Send message ─────────────────────────────────────────────────────────
  const sendMessage = useCallback((text) => {
    const trimmed = text.trim();
    if (!trimmed || isTyping) return;

    setLastFailed(null);
    setMessages(prev => [...prev, { id: nextId(), type: 'user', text: trimmed, dir: detectDir(trimmed) }]);
    setIsTyping(true);

    sendChatMessage(trimmed, sessionUuid, getLead(), language)
      .then(data => {
        if (data.session_uuid && !sessionUuid) {
          setSessionUuid(data.session_uuid);
          saveStoredSession(data.session_uuid);
        }
        const { text: aiText, cardData, dir } = parseAiResponse(data.message);
        setSessionDir(dir);
        setMessages(prev => [...prev, { id: nextId(), type: 'ai', text: aiText, cardData, dir }]);
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
  }, [isTyping, sessionUuid, language]);

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
    setLanguage(null);
    setSessionDir('ltr');
    setMessages(greetingMessage());
  }, []);

  return (
    <div ref={windowRef} className="sac-window" role="dialog" aria-label="Chat window">
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
