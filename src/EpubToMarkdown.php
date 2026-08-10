<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

/**
 * The front door.
 *
 *     $result = (new EpubToMarkdown())->convert('/path/to/book.epub');
 *     $result->markdown;   // whole book, spine order
 *     $result->chapters;   // per-chapter, with titles
 *     $result->outline;    // chapter titles as a level-1 outline
 *
 * Bring your own converter when your corpus already speaks a markdown
 * dialect (one dialect per corpus beats a prettier stranger):
 *
 *     new EpubToMarkdown(fn (string $html): string => MyConverter::convert($html));
 *
 * Laravel needs no service provider — construct it, or bind it in a
 * container as any plain class.
 */
final class EpubToMarkdown
{
    /** @var callable(string): string */
    private $htmlConverter;

    public function __construct(?callable $htmlConverter = null)
    {
        $this->htmlConverter = $htmlConverter ?? [new HtmlConverter(), 'convert'];
    }

    /** @throws Exceptions\InvalidEpub */
    public function convert(string $path): ConversionResult
    {
        $epub = Epub::open($path);

        try {
            $chapters = [];
            $outline = [];

            foreach ($epub->spineHrefs as $href) {
                $xhtml = $epub->read($href);
                if ($xhtml === null) {
                    continue; // manifest lied about this entry; skip, don't die
                }

                $markdown = ($this->htmlConverter)(self::body($xhtml));
                if ($markdown === '') {
                    continue;
                }

                $title = self::chapterTitle($xhtml);
                $chapters[] = new Chapter(href: $href, title: $title, markdown: $markdown);
                if ($title !== null) {
                    $outline[] = ['level' => 1, 'title' => $title];
                }
            }

            if ($chapters === []) {
                throw new Exceptions\InvalidEpub('EPUB tidak memuat teks yang bisa diambil.');
            }

            return new ConversionResult(
                markdown: implode("\n\n", array_map(fn (Chapter $c) => $c->markdown, $chapters)),
                title: $epub->title,
                authors: $epub->authors,
                language: $epub->language,
                chapters: $chapters,
                outline: $outline,
            );
        } finally {
            $epub->close();
        }
    }

    /** Inner <body> when present — chapter chrome stays out of the corpus. */
    private static function body(string $xhtml): string
    {
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $xhtml, $m)) {
            return $m[1];
        }

        return $xhtml;
    }

    private static function chapterTitle(string $xhtml): ?string
    {
        foreach (['h1', 'h2', 'title'] as $tag) {
            if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $xhtml, $m)) {
                $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($title !== '') {
                    return $title;
                }
            }
        }

        return null;
    }
}
