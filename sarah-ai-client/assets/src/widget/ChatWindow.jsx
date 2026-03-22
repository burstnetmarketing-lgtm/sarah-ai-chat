import React, { useState, useCallback, useEffect } from 'react';
import Header from './Header.jsx';
import MessageArea from './MessageArea.jsx';
import InputBox from './InputBox.jsx';
import { sendChatMessage } from './chatApi.js';

let _id = 0;
function nextId() { return ++_id; }

function getGreeting() {
  return (window.SarahAiWidget?.connection?.greeting_message || '').trim();
}

export default function ChatWindow({ onClose }) {
  const [messages, setMessages]       = useState([]);
  const [isTyping, setIsTyping]       = useState(false);
  const [sessionUuid, setSessionUuid] = useState(null);

  // Show greeting instantly on open — no server call needed
  useEffect(() => {
    const greeting = getGreeting();
    if (greeting) {
      setMessages([{ id: nextId(), type: 'ai', text: greeting }]);
    }
  }, []);

  const sendMessage = useCallback((text) => {
    const trimmed = text.trim();
    if (!trimmed || isTyping) return;

    setMessages(prev => [...prev, { id: nextId(), type: 'user', text: trimmed }]);
    setIsTyping(true);

    sendChatMessage(trimmed, sessionUuid)
      .then(data => {
        if (!sessionUuid && data.session_uuid) {
          setSessionUuid(data.session_uuid);
        }
        setMessages(prev => [...prev, { id: nextId(), type: 'ai', text: data.message }]);
      })
      .catch(err => {
        const text = err.message?.includes('not configured')
          ? 'Chat is not configured yet.'
          : 'Unable to connect. Please try again.';
        setMessages(prev => [...prev, { id: nextId(), type: 'ai', text }]);
      })
      .finally(() => setIsTyping(false));
  }, [isTyping, sessionUuid]);

  return (
    <div className="sac-window" role="dialog" aria-label="Chat window">
      <Header onClose={onClose} />
      <MessageArea messages={messages} isTyping={isTyping} onQuickQuestion={sendMessage} />
      <InputBox onSend={sendMessage} disabled={isTyping} />
    </div>
  );
}
