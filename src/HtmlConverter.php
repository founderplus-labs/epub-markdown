<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

/**
 * Deliberately lite HTML → Markdown: headings, paragraphs, lists,
 * blockquotes, emphasis, links, and images-as-alt-text. Everything else is
 * stripped to its text. The goal is deterministic, embedding-friendly
 * markdown — not a perfect round trip.
 *
 * Regex-based on purpose: EPUB chapter XHTML routinely carries doctypes and
 * HTML entities that make strict XML parsing miserable, and a lite dialect
 * has no need for a DOM.
 */
final class HtmlConverter
{
    public function convert(string $html): string
    {
        $md = $html;

        // Normalise whitespace between tags so the block regexes stay stable.
        $md = preg_replace('/>\s+</', '><', $md) ?? $md;

        // Headings.
        for ($i = 6; $i >= 1; $i--) {
            $md = preg_replace_callback(
                "/<h{$i}[^>]*>(.*?)<\/h{$i}>/is",
                fn ($m) => "\n\n" . str_repeat('#', $i) . ' ' . trim(strip_tags($m[1])) . "\n\n",
                $md,
            ) ?? $md;
        }

        // List items — before <p>, so <li><p> does not double the newlines.
        $md = preg_replace_callback(
            '/<li[^>]*>(.*?)<\/li>/is',
            fn ($m) => '- ' . trim(strip_tags($m[1])) . "\n",
            $md,
        ) ?? $md;

        // Blockquotes.
        $md = preg_replace_callback(
            '/<blockquote[^>]*>(.*?)<\/blockquote>/is',
            fn ($m) => "\n> " . trim(strip_tags($m[1])) . "\n\n",
            $md,
        ) ?? $md;

        // Links — keep the URL (retrieval and citation signal).
        $md = preg_replace_callback(
            '/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is',
            fn ($m) => '[' . trim(strip_tags($m[2])) . '](' . $m[1] . ')',
            $md,
        ) ?? $md;

        // Images become their alt text — a book's figures still deserve words.
        $md = preg_replace_callback(
            '/<img[^>]*alt="([^"]*)"[^>]*>/is',
            fn ($m) => trim($m[1]) !== '' ? "\n\n*[Gambar: " . trim($m[1]) . "]*\n\n" : '',
            $md,
        ) ?? $md;

        // Emphasis.
        $md = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $md) ?? $md;
        $md = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $md) ?? $md;

        // Paragraphs and line breaks.
        $md = preg_replace('/<br\s*\/?>/i', "\n", $md) ?? $md;
        $md = preg_replace('/<p[^>]*>/i', "\n\n", $md) ?? $md;
        $md = preg_replace('/<\/?(div|section|article)[^>]*>/i', "\n\n", $md) ?? $md;

        // Everything else: strip.
        $md = strip_tags($md);
        $md = html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Tidy: trailing spaces off, max two consecutive newlines.
        $md = preg_replace("/[ \t]+\n/", "\n", $md) ?? $md;
        $md = preg_replace("/\n{3,}/", "\n\n", $md) ?? $md;

        return trim($md);
    }
}
