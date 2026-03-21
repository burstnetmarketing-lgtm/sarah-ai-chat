import React, { useState, useCallback } from 'react';
import Header from './Header.jsx';
import MessageArea from './MessageArea.jsx';
import InputBox from './InputBox.jsx';

let _id = 0;
function nextId() { return ++_id; }

const MOCK_RESPONSES = [
  'Thanks for your message! How can I help you further?',
  'Great question! Let me look into that for you.',
  'I appreciate you reaching out. Could you tell me a bit more?',
  'Got it! I\'m here to help — what else would you like to know?',
  'Thanks for asking. I\'ll do my best to assist you.',
];

function getMockResponse() {
  return new Promise(resolve => {
    const delay = 800 + Math.random() * 700;
    const text = MOCK_RESPONSES[Math.floor(Math.random() * MOCK_RESPONSES.length)];
    setTimeout(() => resolve(text), delay);
  });
}

export default function ChatWindow({ onClose }) {
  const [messages, setMessages] = useState([]);
  const [isTyping, setIsTyping] = useState(false);

  const sendMessage = useCallback((text) => {
    const trimmed = text.trim();
    if (!trimmed || isTyping) return;

    setMessages(prev => [...prev, { id: nextId(), type: 'user', text: trimmed }]);
    setIsTyping(true);

    getMockResponse().then(response => {
      setMessages(prev => [...prev, { id: nextId(), type: 'ai', text: response }]);
      setIsTyping(false);
    });
  }, [isTyping]);

  return (
    <div className="sac-window" role="dialog" aria-label="Chat window">
      <Header onClose={onClose} />
      <MessageArea messages={messages} isTyping={isTyping} onQuickQuestion={sendMessage} />
      <InputBox onSend={sendMessage} disabled={isTyping} />
    </div>
  );
}
