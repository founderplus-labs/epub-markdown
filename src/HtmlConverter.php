<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

/**
 * Deliberately lite HTML → Markdown: headings, paragraphs, lists,
 * blockquotes, emphasis, links, horizontal rules, and images-as-alt-text.
 * Everything else is stripped to its text. The goal is deterministic,
 * embedding-friendly markdown — not a perfect round trip.
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

        // Normalise whitespace between tags to a single space — keeps the
        // block regexes stable without gluing words together across inline
        // tags ("</em> <strong>" must stay a word boundary).
        $md = preg_replace('/>\s+</', '> <', $md) ?? $md;

        // <br> before the block handlers, so quoted or listed content sees a
        // real newline instead of a tag that strip_tags would swallow.
        $md = preg_replace('/<br\s*\/?>/i', "\n", $md) ?? $md;

        // Headings — one pass covers all six levels.
        $md = preg_replace_callback(
            '/<h([1-6])[^>]*>(.*?)<\/h\1>/is',
            fn ($m) => "\n\n" . str_repeat('#', (int) $m[1]) . ' ' . self::oneLine($m[2]) . "\n\n",
            $md,
        ) ?? $md;

        // List items — before <p>, so <li><p> does not double the newlines.
        $md = preg_replace_callback(
            '/<li[^>]*>(.*?)<\/li>/is',
            fn ($m) => '- ' . self::oneLine($m[1]) . "\n",
            $md,
        ) ?? $md;

        // Blockquotes — every line needs its own "> ", or the quote breaks.
        $md = preg_replace_callback(
            '/<blockquote[^>]*>(.*?)<\/blockquote>/is',
            fn ($m) => "\n\n> " . preg_replace('/[ \t]*\n[ \t]*/', "\n> ", trim(strip_tags($m[1]))) . "\n\n",
            $md,
        ) ?? $md;

        // Links — keep the URL (retrieval and citation signal).
        $md = preg_replace_callback(
            '/<a[^>]*\shref=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            fn ($m) => '[' . self::oneLine($m[3]) . '](' . $m[2] . ')',
            $md,
        ) ?? $md;

        // Images become their alt text — a book's figures still deserve words.
        $md = preg_replace_callback(
            '/<img[^>]*\salt=(["\'])(.*?)\1[^>]*>/is',
            fn ($m) => trim($m[2]) !== '' ? "\n\n*[Gambar: " . trim($m[2]) . "]*\n\n" : '',
            $md,
        ) ?? $md;

        // Emphasis.
        $md = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/is', '**$2**', $md) ?? $md;
        $md = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/is', '*$2*', $md) ?? $md;

        // Horizontal rules.
        $md = preg_replace('/<hr(\s[^>]*)?\/?>/i', "\n\n---\n\n", $md) ?? $md;

        // Block containers become paragraph breaks. The tag name must end at
        // whitespace or ">", so <pre> and <param> stay out of the match.
        $md = preg_replace('/<\/?(p|div|section|article|ul|ol)(\s[^>]*)?>/i', "\n\n", $md) ?? $md;

        // Everything else: strip.
        $md = strip_tags($md);
        $md = html_entity_decode($md, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Tidy: &nbsp; to plain space, space runs to one space, no leading or
        // trailing spaces on a line (leading spaces would read as code
        // blocks), max two consecutive newlines.
        $md = str_replace("\u{00A0}", ' ', $md);
        $md = preg_replace('/[ \t]{2,}/', ' ', $md) ?? $md;
        $md = preg_replace('/[ \t]+\n/', "\n", $md) ?? $md;
        $md = preg_replace('/\n[ \t]+/', "\n", $md) ?? $md;
        $md = preg_replace('/\n{3,}/', "\n\n", $md) ?? $md;

        return trim($md);
    }

    /** Tag-stripped, whitespace-collapsed text for constructs that must stay on one line. */
    private static function oneLine(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }
}
