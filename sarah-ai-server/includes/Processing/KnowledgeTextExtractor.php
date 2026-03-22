<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

/**
 * Extracts plain text from knowledge resources based on their type.
 *
 * All paths converge to a string of usable plain text that the downstream
 * normalization and chunking steps can work with.
 *
 * SUPPORTED TYPES
 * - text  : source_content IS the text
 * - txt   : source_content is text, or a URL to a .txt file
 * - link  : source_content is a URL — fetched and HTML stripped
 * - pdf   : source_content is a URL — fetched and binary-parsed for text
 * - docx  : source_content is a URL — fetched, unzipped, document.xml parsed
 *
 * FAILURE HANDLING
 * On any unrecoverable error, throws \RuntimeException with a descriptive message.
 * The caller (KnowledgeProcessingService) is responsible for catching and recording
 * the failure state.
 */
class KnowledgeTextExtractor
{
    private const FETCH_TIMEOUT = 20;

    /**
     * Extract text from a knowledge resource row.
     *
     * @param  array  $resource  Full resource row from KnowledgeResourceRepository
     * @return string            Extracted plain text (may still need normalization)
     * @throws \RuntimeException if extraction fails
     */
    public function extract(array $resource): string
    {
        $type    = strtolower(trim((string) ($resource['resource_type'] ?? '')));
        $content = (string) ($resource['source_content'] ?? '');

        switch ($type) {
            case 'text':
                return $this->extractText($content);
            case 'txt':
                return $this->extractTxt($content);
            case 'link':
                return $this->extractLink($content);
            case 'pdf':
                return $this->extractPdf($content);
            case 'docx':
                return $this->extractDocx($content);
            default:
                // Unknown types: treat source_content as plain text if non-empty
                if (trim($content) !== '') {
                    return $content;
                }
                throw new \RuntimeException("Unsupported resource type '{$type}' with no source content.");
        }
    }

    // ─── Type-specific extractors ────────────────────────────────────────────

    private function extractText(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new \RuntimeException('Text resource has empty source_content.');
        }
        return $trimmed;
    }

    private function extractTxt(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw new \RuntimeException('TXT resource has empty source_content.');
        }
        // If it looks like a URL, fetch it
        if (preg_match('#^https?://#i', $trimmed)) {
            $fetched = $this->fetchUrl($trimmed);
            return trim($fetched);
        }
        return $trimmed;
    }

    private function extractLink(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new \RuntimeException('Link resource has empty source_content (no URL).');
        }
        if (! preg_match('#^https?://#i', $url)) {
            throw new \RuntimeException("Link resource source_content is not a valid URL: '{$url}'");
        }

        $html = $this->fetchUrl($url);

        // Remove script, style, nav, footer, header elements
        $html = preg_replace('#<(script|style|nav|footer|header|aside)[^>]*>.*?</\1>#si', '', $html);
        // Strip all remaining tags
        $text = wp_strip_all_tags($html, true);
        // Collapse whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException("No usable text could be extracted from URL: '{$url}'");
        }
        return $text;
    }

    private function extractPdf(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            throw new \RuntimeException('PDF resource has empty source_content.');
        }

        if (preg_match('#^https?://#i', $source)) {
            $binary = $this->fetchUrlBinary($source);
        } elseif (file_exists($source)) {
            $binary = file_get_contents($source);
            if ($binary === false) {
                throw new \RuntimeException("Could not read PDF file from disk: '{$source}'");
            }
        } else {
            throw new \RuntimeException("PDF source_content is not a valid URL or file path: '{$source}'");
        }

        if (substr($binary, 0, 4) !== '%PDF') {
            throw new \RuntimeException('PDF resource content does not appear to be a valid PDF (missing %PDF header).');
        }

        $text = $this->parsePdfText($binary);
        $text = trim($text);

        if ($text === '') {
            throw new \RuntimeException('Could not extract any text from PDF. The file may be image-based or encrypted.');
        }
        return $text;
    }

    private function extractDocx(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            throw new \RuntimeException('DOCX resource has empty source_content.');
        }

        if (preg_match('#^https?://#i', $source)) {
            // Fetch and write to temp file
            $binary  = $this->fetchUrlBinary($source);
            $tmpFile = wp_tempnam('sarah_docx_');
            if ($tmpFile === false || $tmpFile === '') {
                throw new \RuntimeException('Could not create temporary file for DOCX extraction.');
            }
            try {
                file_put_contents($tmpFile, $binary);
                $text = $this->parseDocxText($tmpFile);
            } finally {
                @unlink($tmpFile);
            }
        } elseif (file_exists($source)) {
            // Already on disk — read directly
            $text = $this->parseDocxText($source);
        } else {
            throw new \RuntimeException("DOCX source_content is not a valid URL or file path: '{$source}'");
        }

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Could not extract any text from DOCX file.');
        }
        return $text;
    }

    // ─── File parsers ────────────────────────────────────────────────────────

    /**
     * Extract text from a PDF binary.
     *
     * Strategy:
     * 1. Collect all stream segments — try gzuncompress (zlib) and gzinflate (deflate raw)
     * 2. For each segment: extract text from BT/ET blocks
     *    - literal strings  (text) Tj / TJ
     *    - hex strings      <ABCDEF> Tj / TJ
     * 3. Decode UTF-16BE (common in CIDFont PDFs and many Word-exported PDFs)
     * 4. Fallback: scan raw binary for printable ASCII strings
     *
     * Image-only PDFs and heavily encrypted PDFs will still yield empty result.
     */
    private function parsePdfText(string $binary): string
    {
        // Collect content sources: raw binary + all decompressed streams
        $sources = [$binary];

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $streamMatches);
        foreach ($streamMatches[1] as $stream) {
            // zlib (FlateDecode with header)
            if (function_exists('gzuncompress')) {
                $raw = @gzuncompress($stream);
                if ($raw !== false) {
                    $sources[] = $raw;
                }
            }
            // deflate raw (FlateDecode without header — common in many generators)
            if (function_exists('gzinflate')) {
                $raw = @gzinflate($stream);
                if ($raw !== false) {
                    $sources[] = $raw;
                }
            }
        }

        $parts = [];
        foreach ($sources as $src) {
            foreach ($this->extractPartsFromContent($src) as $p) {
                $parts[] = $p;
            }
        }

        // Last-resort fallback: printable ASCII runs ≥ 4 chars from the raw binary
        if (empty($parts)) {
            preg_match_all('/[ -~]{4,}/', $binary, $m);
            foreach ($m[0] as $run) {
                $run = trim($run);
                // Skip obvious PDF structure keywords
                if ($run && ! preg_match('/^(stream|endstream|obj|endobj|xref|trailer|startxref|PDF|BT|ET|Tj|TJ)$/', $run)) {
                    $parts[] = $run;
                }
            }
        }

        $text = implode(' ', array_filter(array_map('trim', $parts)));
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return $text;
    }

    /**
     * Extract text parts from a single content buffer (raw or decompressed stream).
     *
     * @return string[]
     */
    private function extractPartsFromContent(string $content): array
    {
        $parts = [];

        // ── BT ... ET blocks ─────────────────────────────────────────────────
        preg_match_all('/BT\s+(.*?)\s*ET/s', $content, $btMatches);
        foreach ($btMatches[1] as $block) {

            // Literal strings: (text) Tj  |  (text) '  |  (text) "
            preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*(?:Tj|\'|")/', $block, $m);
            foreach ($m[1] as $t) {
                $decoded = $this->decodeLiteralString($t);
                if (trim($decoded) !== '') {
                    $parts[] = $decoded;
                }
            }

            // Hex strings: <ABCDEF> Tj
            preg_match_all('/<([0-9a-fA-F\s]+)>\s*(?:Tj|\'|")/', $block, $m);
            foreach ($m[1] as $hex) {
                $decoded = $this->decodeHexString($hex);
                if (trim($decoded) !== '') {
                    $parts[] = $decoded;
                }
            }

            // TJ arrays: [(text) -200 (more)] TJ
            preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $m);
            foreach ($m[1] as $arr) {
                // Literal elements
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $arr, $sm);
                foreach ($sm[1] as $t) {
                    $decoded = $this->decodeLiteralString($t);
                    if (trim($decoded) !== '') {
                        $parts[] = $decoded;
                    }
                }
                // Hex elements
                preg_match_all('/<([0-9a-fA-F\s]+)>/', $arr, $sm);
                foreach ($sm[1] as $hex) {
                    $decoded = $this->decodeHexString($hex);
                    if (trim($decoded) !== '') {
                        $parts[] = $decoded;
                    }
                }
            }
        }

        // ── Outside BT/ET: scan for any Tj/TJ strings ────────────────────────
        if (empty($parts)) {
            preg_match_all('/\(([A-Za-z0-9 ,.\-!?:;\'"]{3,})\)\s*(?:Tj|TJ|\')/', $content, $m);
            foreach ($m[1] as $t) {
                if (trim($t) !== '') {
                    $parts[] = $t;
                }
            }
        }

        return $parts;
    }

    /**
     * Decode a PDF literal string — escape sequences + UTF-16BE.
     */
    private function decodeLiteralString(string $s): string
    {
        // Standard PDF escape sequences
        $s = str_replace(
            ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
            ["\n",  "\r",  "\t",  '(',   ')',   '\\'],
            $s
        );
        return $this->decodeUtf16IfNeeded($s);
    }

    /**
     * Decode a hex-encoded PDF string (e.g. <00410042> → "AB").
     * Handles both plain PDFDocEncoding and UTF-16BE.
     */
    private function decodeHexString(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex);
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 !== 0) {
            $hex .= '0'; // Pad to even length per spec
        }

        $bytes = pack('H*', $hex);
        return $this->decodeUtf16IfNeeded($bytes);
    }

    /**
     * If the byte string looks like UTF-16BE (BOM or null-byte pattern), convert it.
     * Otherwise return as-is (treated as PDFDocEncoding / Latin-1).
     */
    private function decodeUtf16IfNeeded(string $s): string
    {
        if (strlen($s) < 2) {
            return $s;
        }

        // Explicit UTF-16BE BOM: FE FF
        if ($s[0] === "\xFE" && $s[1] === "\xFF") {
            $decoded = @mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE');
            return ($decoded !== false && $decoded !== '') ? $decoded : $s;
        }

        // Heuristic: if many even-indexed bytes are 0x00 it is likely UTF-16BE
        if (strlen($s) >= 4 && strlen($s) % 2 === 0) {
            $sample   = min(16, strlen($s));
            $nullsAt0 = 0;
            for ($i = 0; $i < $sample; $i += 2) {
                if (ord($s[$i]) === 0) {
                    $nullsAt0++;
                }
            }
            if ($nullsAt0 >= ($sample / 4)) {
                $decoded = @mb_convert_encoding($s, 'UTF-8', 'UTF-16BE');
                if ($decoded !== false && preg_match('/[\x20-\x7E]{2,}/u', $decoded)) {
                    return $decoded;
                }
            }
        }

        // Strip non-printable bytes and return as Latin-1
        return preg_replace('/[\x00-\x08\x0E-\x1F\x7F]/', '', $s);
    }

    /**
     * Extract text from DOCX (ZIP archive containing word/document.xml).
     */
    private function parseDocxText(string $filePath): string
    {
        if (! class_exists('ZipArchive')) {
            throw new \RuntimeException('PHP ZipArchive extension is not available — cannot extract DOCX content.');
        }

        $zip = new \ZipArchive();
        $res = $zip->open($filePath);
        if ($res !== true) {
            throw new \RuntimeException("Could not open DOCX as ZIP archive (ZipArchive error code: {$res}).");
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('word/document.xml not found inside DOCX archive.');
        }

        // Remove XML namespaces and parse
        $xml = preg_replace('/xmlns[^=]*="[^"]*"/', '', $xml);
        $xml = preg_replace('/[a-zA-Z]+:([a-zA-Z])/', '$1', $xml);

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($doc === false) {
            // Fallback: strip all XML tags
            return strip_tags($xml);
        }

        // Extract text from <w:t> (text run) elements in paragraph order
        $paragraphs = [];
        $body       = $doc->body ?? $doc;

        foreach ($body->p ?? [] as $para) {
            $paraText = '';
            foreach ($para->r ?? [] as $run) {
                $t = (string) ($run->t ?? '');
                if ($t !== '') {
                    $paraText .= $t;
                }
            }
            // Also handle hyperlinks and other inline elements
            foreach ($para->hyperlink ?? [] as $link) {
                foreach ($link->r ?? [] as $run) {
                    $paraText .= (string) ($run->t ?? '');
                }
            }
            if (trim($paraText) !== '') {
                $paragraphs[] = trim($paraText);
            }
        }

        if (empty($paragraphs)) {
            // Fallback: strip all XML tags
            return trim(strip_tags($xml));
        }

        return implode("\n", $paragraphs);
    }

    // ─── HTTP helpers ────────────────────────────────────────────────────────

    private function fetchUrl(string $url): string
    {
        $response = wp_remote_get($url, [
            'timeout'    => self::FETCH_TIMEOUT,
            'user-agent' => 'SarahAI-KnowledgeProcessor/1.0',
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException("Failed to fetch URL '{$url}': " . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new \RuntimeException("URL '{$url}' returned HTTP {$code}.");
        }

        return wp_remote_retrieve_body($response);
    }

    private function fetchUrlBinary(string $url): string
    {
        return $this->fetchUrl($url);
    }
}
