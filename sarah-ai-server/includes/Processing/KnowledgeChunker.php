<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

/**
 * Splits normalized plain text into overlapping chunks suitable for embedding.
 *
 * STRATEGY
 * Paragraph-aware sliding window:
 *   1. Split text into paragraphs on blank lines.
 *   2. Accumulate paragraphs into a window until the char limit is reached.
 *   3. When the window is full, emit a chunk, then slide forward by (size - overlap).
 *   4. If a single paragraph exceeds the chunk size, hard-split it by sentences,
 *      then by character if needed.
 *
 * TUNING
 * Default chunk size: 1 500 chars. Default overlap: 200 chars.
 * These values work well with text-embedding-3-small (8 191 token context).
 * At ~4 chars/token a 1 500-char chunk is ~375 tokens — well within model limits
 * and leaves room for prompt overhead.
 *
 * OVERLAP PURPOSE
 * Overlap preserves sentence context at boundaries, preventing the retrieval system
 * from losing meaning that straddles two consecutive chunks.
 */
class KnowledgeChunker
{
    private int $chunkSize;
    private int $overlap;

    public function __construct(int $chunkSize = 1500, int $overlap = 200)
    {
        $this->chunkSize = max(200, $chunkSize);
        $this->overlap   = max(0, min($overlap, (int) ($chunkSize / 2)));
    }

    /**
     * Chunk normalized text into an array of chunk descriptors.
     *
     * @param  string $text  Normalized plain text
     * @return array         [['index' => int, 'text' => string, 'token_count' => int|null], ...]
     */
    public function chunk(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        // Split into paragraphs (blank line separator)
        $paragraphs = preg_split('/\n{2,}/', $text);
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        $rawChunks = [];
        $buffer    = '';

        foreach ($paragraphs as $para) {
            if (mb_strlen($para) > $this->chunkSize) {
                // Flush buffer first
                if ($buffer !== '') {
                    $rawChunks[] = $buffer;
                    $buffer = '';
                }
                // Hard-split the oversized paragraph
                foreach ($this->splitLargeParagraph($para) as $piece) {
                    $rawChunks[] = $piece;
                }
                continue;
            }

            $candidate = $buffer === '' ? $para : $buffer . "\n\n" . $para;

            if (mb_strlen($candidate) <= $this->chunkSize) {
                $buffer = $candidate;
            } else {
                // Buffer is full — emit it, then start new buffer with overlap
                if ($buffer !== '') {
                    $rawChunks[] = $buffer;
                    $overlap     = $this->trailOf($buffer);
                    $buffer      = $overlap === '' ? $para : $overlap . "\n\n" . $para;
                } else {
                    $rawChunks[] = $para;
                }
            }
        }

        // Flush remaining buffer
        if ($buffer !== '') {
            $rawChunks[] = $buffer;
        }

        // Build final chunk descriptors
        $chunks = [];
        foreach ($rawChunks as $idx => $chunkText) {
            $chunkText = trim($chunkText);
            if ($chunkText === '') {
                continue;
            }
            $chunks[] = [
                'index'       => $idx,
                'text'        => $chunkText,
                'token_count' => $this->estimateTokenCount($chunkText),
            ];
        }

        return $chunks;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Split a paragraph that exceeds chunk size into sentence-based pieces,
     * falling back to hard character splits if needed.
     */
    private function splitLargeParagraph(string $para): array
    {
        // Try sentence splitting first
        $sentences = preg_split('/(?<=[.!?])\s+/', $para, -1, PREG_SPLIT_NO_EMPTY);
        $pieces    = [];
        $buf       = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($sentence) > $this->chunkSize) {
                // Hard split the sentence
                if ($buf !== '') {
                    $pieces[] = $buf;
                    $buf      = '';
                }
                foreach ($this->hardSplit($sentence) as $piece) {
                    $pieces[] = $piece;
                }
                continue;
            }

            $candidate = $buf === '' ? $sentence : $buf . ' ' . $sentence;
            if (mb_strlen($candidate) <= $this->chunkSize) {
                $buf = $candidate;
            } else {
                if ($buf !== '') {
                    $pieces[] = $buf;
                }
                $buf = $sentence;
            }
        }

        if ($buf !== '') {
            $pieces[] = $buf;
        }

        return $pieces;
    }

    /** Hard-split a string into chunks of at most $chunkSize chars. */
    private function hardSplit(string $text): array
    {
        $pieces = [];
        $len    = mb_strlen($text);
        $pos    = 0;
        while ($pos < $len) {
            $pieces[] = mb_substr($text, $pos, $this->chunkSize);
            $pos += $this->chunkSize;
        }
        return $pieces;
    }

    /**
     * Return the trailing $overlap chars of $text to use as overlap for the next chunk.
     * Tries to break at a word boundary rather than mid-word.
     */
    private function trailOf(string $text): string
    {
        if ($this->overlap <= 0) {
            return '';
        }
        $len   = mb_strlen($text);
        $start = max(0, $len - $this->overlap);
        $trail = mb_substr($text, $start);
        // Find first space to avoid cutting mid-word
        $spacePos = mb_strpos($trail, ' ');
        if ($spacePos !== false && $spacePos < 50) {
            $trail = mb_substr($trail, $spacePos + 1);
        }
        return trim($trail);
    }

    /**
     * Rough token count estimate: 1 token ≈ 4 chars for English text.
     * Used for display/logging only — not for model decisions.
     */
    private function estimateTokenCount(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
