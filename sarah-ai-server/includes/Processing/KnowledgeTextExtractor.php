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

        // Fetch if URL, otherwise treat as raw binary content
        if (preg_match('#^https?://#i', $source)) {
            $binary = $this->fetchUrlBinary($source);
        } else {
            $binary = $source;
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

        // Fetch if URL
        if (preg_match('#^https?://#i', $source)) {
            $binary = $this->fetchUrlBinary($source);
        } else {
            $binary = $source;
        }

        // Write to temp file so ZipArchive can read it
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

        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Could not extract any text from DOCX file.');
        }
        return $text;
    }

    // ─── File parsers ────────────────────────────────────────────────────────

    /**
     * Extract text from PDF binary using regex-based stream parsing.
     * Works for simple text-based PDFs. Image-based PDFs will yield empty result.
     */
    private function parsePdfText(string $binary): string
    {
        $text = '';

        // Decompress any FlateDecode streams first
        if (function_exists('gzuncompress')) {
            // Find all compressed streams and try to decompress
            preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $streamMatches);
            $decompressed = '';
            foreach ($streamMatches[1] as $stream) {
                $raw = @gzuncompress($stream);
                if ($raw !== false) {
                    $decompressed .= $raw . "\n";
                }
            }
            if ($decompressed !== '') {
                $binary = $decompressed;
            }
        }

        // Extract text from BT...ET blocks
        preg_match_all('/BT\s+(.*?)\s*ET/s', $binary, $btMatches);
        $parts = [];
        foreach ($btMatches[1] as $block) {
            // Match all text-showing operators: Tj, ' (single quote), " (double quote), TJ
            preg_match_all(
                '/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*(?:Tj|\'|")|(\[.*?\])\s*TJ/s',
                $block,
                $textMatches
            );

            foreach ($textMatches[1] as $t) {
                if ($t !== '') {
                    $parts[] = $this->decodePdfString($t);
                }
            }

            // Handle TJ arrays
            foreach ($textMatches[2] as $arr) {
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $arr, $arrMatches);
                foreach ($arrMatches[1] as $t) {
                    if ($t !== '') {
                        $parts[] = $this->decodePdfString($t);
                    }
                }
            }
        }

        // Also try to extract raw text strings outside BT/ET (some PDFs)
        if (empty($parts)) {
            preg_match_all('/\(([A-Za-z0-9 ,.\-!?:;\'"]{4,})\)\s*Tj/', $binary, $rawMatches);
            foreach ($rawMatches[1] as $t) {
                $parts[] = $t;
            }
        }

        $text = implode(' ', array_filter(array_map('trim', $parts)));
        // Collapse excessive whitespace
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return $text;
    }

    private function decodePdfString(string $s): string
    {
        // Decode common PDF escape sequences
        $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $s);
        return $s;
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
