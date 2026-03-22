# Phase 6.2 — System Design Summary
## Retrieval & RAG Runtime

---

## 1. Overview

Phase 6.2 uses the processed knowledge (chunks + embeddings from Phase 6.1) during chat runtime.

For each user message, the system retrieves the most relevant chunks and injects them into the prompt so the agent answers based on site knowledge instead of general guesses.

---

## 2. Primary Objective

At runtime, the system must:

- accept a user question
- compute an embedding for the question
- retrieve the most relevant chunks from the knowledge store
- inject selected context into the system prompt
- generate the final answer using the agent

---

## 3. Runtime Flow

```
User message
  ↓
Create query embedding
  ↓
Semantic search over stored chunk embeddings
  ↓
Select top-K relevant chunks
  ↓
Build prompt (agent behavior + retrieved context)
  ↓
Call LLM (OpenAI or provider)
  ↓
Return answer
```

---

## 4. Retrieval Requirements

The retrieval layer must:

- perform similarity search using embeddings
- support filtering by:
  - site
  - agent (if applicable)
  - resource status (only active/processed)
- return ordered results (most relevant first)
- limit results (top-K)

The system must avoid returning excessive or irrelevant context.

---

## 5. Context Injection

Retrieved chunks must be appended to the prompt in a structured way:

```
## Knowledge Base
- Chunk 1
- Chunk 2
- Chunk 3
```

Rules:

- keep context concise
- avoid duplication
- preserve chunk readability
- do not mix unrelated chunks

---

## 6. Prompt Integration

The final prompt must combine:

- agent behavior (role, tone, guardrails)
- site identity (if configured)
- retrieved knowledge context

The knowledge section must not override behavior rules.

---

## 7. Safety & Guardrails

The agent must:

- prioritize retrieved knowledge when answering
- avoid hallucinating beyond provided context
- clearly state uncertainty if context is insufficient

---

## 8. Performance Considerations

- retrieval must be fast (indexed search)
- avoid embedding the same query multiple times
- limit token usage by controlling context size

---

## 9. Non-Goals

This phase does NOT include:

- advanced ranking (re-ranking models)
- multi-step reasoning chains
- cross-resource summarization
- analytics dashboards

---

## 10. Success Criteria

This phase is complete when:

- user questions trigger retrieval over stored knowledge
- relevant chunks are injected into the prompt
- answers reflect actual site knowledge
- hallucination is reduced compared to Phase 5
- runtime remains stable and performant

---

## 11. Phase Boundary

After Phase 6.2:

- the agent is knowledge-aware
- responses are grounded in site data
- the system is ready for further optimization (ranking, caching, analytics)

---

*Generated: Phase 6.2*
