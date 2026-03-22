# Phase 6.1 — System Design Summary
## Knowledge Processing Pipeline

---

## 1. Overview

Phase 6.1 introduces the processing pipeline that transforms stored knowledge resources into retrieval-ready data.

By the end of Phase 5, the system already has:
- a real AI agent runtime
- site-owned knowledge resources
- agent behavior control
- session-aware chat flow

However, knowledge resources are still only stored as source content. They are not yet transformed into a structure that can be searched and injected into prompts at runtime.

This phase is responsible for preparing knowledge for retrieval. It does not yet decide which knowledge should be used for a user question. Instead, it creates the processed artifacts that later retrieval logic will depend on.

The output of this phase must be a stable and repeatable pipeline that can take supported knowledge resources, extract usable text, break that text into meaningful chunks, generate embeddings for those chunks, and persist the results in a way that later runtime retrieval can use efficiently.

This phase is a preparation layer. It is not the retrieval layer itself.

---

## 2. Primary Objective

The goal of this phase is to convert raw knowledge resources into retrieval-ready representations.

At the end of this phase, the system must be able to:

- identify processable knowledge resources
- extract usable text from supported resource types
- normalize that text into a clean processing form
- split the text into chunks suitable for semantic retrieval
- generate embeddings for each chunk
- persist chunk and embedding data for future retrieval
- track processing lifecycle and failure states
- reprocess resources when the source content changes

The phase must treat this as a structured pipeline rather than a one-off transformation.

---

## 3. Supported Resource Types

This phase must support the following knowledge resource types:

- `text`
- `link`
- `pdf`
- `docx`
- `txt`

These types must be treated explicitly and intentionally.

The system must not rely on a generic `file` type at processing time. Each supported type must follow a clear extraction path appropriate to its format.

All supported resource types must ultimately converge into the same downstream pipeline:

```text
raw source → extracted text → cleaned text → chunks → embeddings
```

This is important because retrieval should work on normalized textual content, not on original file formats.

---

## 4. Resource Processing Responsibilities

### 4.1 Text Resources

Text resources are the simplest case. The stored source content already represents the text to be processed.

The pipeline must:
- validate that text exists
- normalize whitespace and trivial formatting issues
- pass the cleaned result into chunking

### 4.2 Link Resources

Link resources must be fetched and converted into usable text.

The processing layer must:
- fetch the remote content
- extract meaningful visible text
- discard obvious markup noise where possible
- produce a text representation suitable for chunking

The first implementation does not need to become a full web crawler. It only needs to process a single provided link in a controlled and predictable way.

### 4.3 PDF Resources

PDF resources must be parsed into text.

The processing layer must:
- read the PDF source
- extract usable textual content
- tolerate extraction failure safely
- move failed resources into a processing-failure state rather than corrupting the pipeline

OCR for image-based PDFs is not required in this phase unless already available. It is acceptable to support text-based PDFs only in the first version, as long as failure states are explicit and safe.

### 4.4 DOCX Resources

DOCX resources must be converted into extracted text.

The processing layer must:
- read structured document content
- preserve readable text order as much as reasonably possible
- ignore rich formatting that is not important for retrieval

### 4.5 TXT Resources

TXT resources must be treated as direct plain text input.

The processing layer must:
- read raw text safely
- normalize encoding and spacing where needed
- pass the cleaned result into chunking

---

## 5. Text Normalization

Before chunking begins, the system must normalize extracted text into a cleaner and more consistent form.

This does not require aggressive rewriting or summarization. The purpose is to remove noise that harms chunking and retrieval.

The normalization layer should aim to:

- reduce excessive whitespace
- remove obviously broken formatting where possible
- preserve meaningful content order
- keep headings, lists, and readable sections if available
- avoid destroying information that may later help retrieval quality

The system must not over-process the content to the point where the original meaning is changed.

---

## 6. Chunking

Chunking is the process of splitting processed text into smaller, retrieval-friendly units.

The system must not treat an entire resource as a single embedding unit if the resource is too large. Instead, it must create smaller pieces that can later be searched, ranked, and injected into prompts.

Chunking must aim to produce chunks that are:

- small enough for semantic search to be useful
- large enough to preserve meaning
- consistent enough to make retrieval reliable
- traceable back to the original resource

The exact chunking strategy is up to the coding agent, but the design should assume:

- chunks may need overlap
- large resources may produce many chunks
- chunk order matters
- later retrieval may need chunk-level metadata

The processing layer must not assume that chunking is a one-time static design forever. Future tuning of chunk size or overlap must remain possible without redesigning ownership models.

---

## 7. Embedding Generation

After chunking, the system must generate embeddings for each chunk.

This phase may use OpenAI embeddings as the first implementation, but the processing architecture should remain provider-aware rather than permanently locked to one embedding source.

The system must be able to:

- submit each chunk for embedding
- receive embedding vectors
- associate those vectors with the correct chunk and resource
- tolerate provider failure without corrupting processed data

The embedding layer must not assume that chunk text is the only future input. Metadata and processing strategy may evolve later, but this phase should build a clean first version using chunk text as the primary embedding source.

---

## 8. Persistence Requirements

This phase must persist the outputs of processing in a form that later retrieval can use.

At minimum, the system must be able to preserve:

- which resource produced which processed output
- which chunks belong to which resource
- chunk order and identity
- embedding data associated with each chunk
- processing status per resource
- failure state if processing or embedding fails
- ability to re-run processing when needed

The coding agent is expected to inspect the current Phase 4.1 and 4.2 database model and extend it appropriately. The scenario intentionally does not dictate exact table structures, but the resulting design must support chunk-level retrieval and reprocessing safely.

---

## 9. Processing Lifecycle

Knowledge processing must be lifecycle-aware.

The system must support at least the idea that a resource may be:

- not yet processed
- queued or pending processing
- actively processing
- successfully processed and retrieval-ready
- failed during extraction or embedding
- needing reprocessing after source changes

The platform must make it possible to distinguish a stored knowledge resource from a retrieval-ready resource.

Storage alone is not enough. A resource is only usable by later RAG runtime when processing has completed successfully.

---

## 10. Reprocessing Behavior

The system must support reprocessing.

This means that if a resource is edited, replaced, or otherwise changed, the system must be able to:

- invalidate old processed output
- generate a new processed representation
- refresh chunks
- refresh embeddings

The implementation must avoid leaving orphaned or inconsistent processed data when a resource changes.

This is important because knowledge will evolve over time, and retrieval quality depends on processed data staying aligned with the current source.

---

## 11. Error Handling

The processing pipeline must handle failure explicitly.

Examples include:

- invalid or unreachable link
- unreadable PDF
- malformed DOCX
- empty extracted text
- embedding API failure
- timeout during processing

Failures must not silently pass as successful processing. They must leave the resource in a state that can be inspected, retried, or repaired later.

The system must avoid partially writing inconsistent data when a resource fails partway through processing.

---

## 12. Processing Trigger Strategy

This phase must define how processing starts.

The first implementation may choose one of these strategies:

- process immediately when a resource is created or activated
- process through an explicit admin-triggered action
- process through an internal queue-like step if already available

The exact trigger strategy is up to the coding agent, but the implementation must remain clear and predictable.

What matters most is that the processing lifecycle is explicit and that the system can safely retry or re-run processing later.

---

## 13. Separation from Retrieval Runtime

This phase must stop at retrieval readiness.

It must NOT yet:

- perform semantic search
- choose relevant chunks for a user question
- inject retrieved chunks into the prompt
- rank chunks during chat runtime
- change agent answer logic

Those belong to Phase 6.2.

The purpose of this phase is to ensure that retrieval-ready data exists before runtime retrieval is introduced.

---

## 14. OpenAI Usage in This Phase

If OpenAI is used for embeddings in the first implementation, that is acceptable.

However, the system should not embed provider assumptions so deeply that later replacement becomes difficult.

The processing layer should keep embedding generation behind a clear abstraction or at least a contained service boundary so that future changes such as:

- different embedding models
- provider switching
- self-hosted embeddings
- batched embedding strategies

can be introduced without redesigning the entire processing flow.

---

## 15. Non-Goals

This phase must not include:

- retrieval query logic
- runtime prompt injection
- final answer generation changes
- ranking and reranking
- dashboard analytics
- advanced vector database infrastructure if not required yet
- OCR pipelines for image-heavy files
- cross-resource summarization

Those belong to later work.

---

## 16. Success Criteria

This phase is complete when the system can take supported knowledge resources (`text`, `link`, `pdf`, `docx`, `txt`), extract usable text, normalize it, chunk it, generate embeddings for those chunks, persist the processed outputs safely, track processing lifecycle and failures, and prepare the knowledge base for real retrieval in Phase 6.2.

At that point, knowledge is no longer just stored content. It becomes retrieval-ready data.

---

## 17. Phase Boundary

Phase 6.1 ends once the knowledge processing pipeline is complete and chunk-level embedding data is available for future retrieval.

Phase 6.2 will use those processed chunks and embeddings during chat runtime to find relevant knowledge and inject it into the agent prompt.
