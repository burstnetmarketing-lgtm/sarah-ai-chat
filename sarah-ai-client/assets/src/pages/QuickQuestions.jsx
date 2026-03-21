import React, { useState, useEffect } from 'react';
import { apiFetch } from '../api/client.js';

export default function QuickQuestions() {
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading]     = useState(true);
  const [newText, setNewText]     = useState('');
  const [adding, setAdding]       = useState(false);
  const [editId, setEditId]       = useState(null);
  const [editText, setEditText]   = useState('');

  useEffect(() => {
    apiFetch('quick-questions')
      .then(res => { if (res.success) setQuestions(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  function handleAdd(e) {
    e.preventDefault();
    const text = newText.trim();
    if (!text) return;
    setAdding(true);
    apiFetch('quick-questions', 'POST', { question: text })
      .then(res => {
        if (res.success) {
          setQuestions(q => [...q, res.data]);
          setNewText('');
        }
      })
      .catch(() => {})
      .finally(() => setAdding(false));
  }

  function handleToggle(q) {
    const next = !(parseInt(q.is_enabled) === 1);
    setQuestions(qs => qs.map(x => x.id === q.id ? { ...x, is_enabled: next ? '1' : '0' } : x));
    apiFetch(`quick-questions/${q.id}`, 'PUT', { question: q.question, is_enabled: next })
      .catch(() => {
        setQuestions(qs => qs.map(x => x.id === q.id ? { ...x, is_enabled: q.is_enabled } : x));
      });
  }

  function handleDelete(id) {
    setQuestions(qs => qs.filter(x => x.id !== id));
    apiFetch(`quick-questions/${id}`, 'DELETE').catch(() => {});
  }

  function startEdit(q) {
    setEditId(q.id);
    setEditText(q.question);
  }

  function handleEditSave(q) {
    const text = editText.trim();
    if (!text) return;
    setQuestions(qs => qs.map(x => x.id === q.id ? { ...x, question: text } : x));
    setEditId(null);
    apiFetch(`quick-questions/${q.id}`, 'PUT', { question: text, is_enabled: parseInt(q.is_enabled) === 1 })
      .catch(() => {});
  }

  if (loading) return <p className="text-muted small p-3">Loading...</p>;

  return (
    <>
      <div className="mb-3">
        <h1 className="h5 fw-semibold text-dark mb-1">Quick Questions</h1>
        <p className="text-muted small mb-0">Define default questions shown to visitors in the chat widget.</p>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Add Question</h6>
        </div>
        <div className="card-body">
          <form onSubmit={handleAdd} className="d-flex gap-2">
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder="e.g. What are your working hours?"
              value={newText}
              onChange={e => setNewText(e.target.value)}
              disabled={adding}
            />
            <button type="submit" className="btn btn-primary btn-sm text-nowrap" disabled={adding || !newText.trim()}>
              {adding ? 'Adding...' : 'Add'}
            </button>
          </form>
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom">
          <h6 className="mb-0 fw-semibold">Questions ({questions.length})</h6>
        </div>
        {questions.length === 0 ? (
          <div className="card-body text-muted small">No questions yet. Add one above.</div>
        ) : (
          <ul className="list-group list-group-flush">
            {questions.map(q => (
              <li key={q.id} className="list-group-item d-flex align-items-center gap-2 py-2">
                {editId === q.id ? (
                  <>
                    <input
                      type="text"
                      className="form-control form-control-sm flex-grow-1"
                      value={editText}
                      onChange={e => setEditText(e.target.value)}
                      onKeyDown={e => e.key === 'Enter' && handleEditSave(q)}
                      autoFocus
                    />
                    <button className="btn btn-success btn-sm" onClick={() => handleEditSave(q)}>Save</button>
                    <button className="btn btn-outline-secondary btn-sm" onClick={() => setEditId(null)}>Cancel</button>
                  </>
                ) : (
                  <>
                    <span
                      className="flex-grow-1 small"
                      style={{ opacity: parseInt(q.is_enabled) === 1 ? 1 : 0.45 }}
                    >
                      {q.question}
                    </span>
                    <div className="form-check form-switch mb-0 me-1">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        role="switch"
                        checked={parseInt(q.is_enabled) === 1}
                        onChange={() => handleToggle(q)}
                        style={{ cursor: 'pointer', width: '2em', height: '1em' }}
                      />
                    </div>
                    <button className="btn btn-outline-secondary btn-sm" onClick={() => startEdit(q)}>Edit</button>
                    <button className="btn btn-outline-danger btn-sm" onClick={() => handleDelete(q.id)}>Delete</button>
                  </>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  );
}
