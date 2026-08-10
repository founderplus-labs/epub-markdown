# epub-markdown

Convert EPUB books to clean Markdown. Spine-ordered chapters, metadata,
a level-1 outline, and a pluggable HTML converter — with zero dependencies
beyond `ext-zip` and `ext-dom`.

Built for retrieval pipelines (chunking, embedding, search) where you want
deterministic, embedding-friendly markdown rather than a pixel-perfect
round trip.

## Install

```bash
composer require founderplus-labs/epub-markdown
```

## Use

```php
use FounderplusLabs\EpubMarkdown\EpubToMarkdown;

$result = (new EpubToMarkdown())->convert('/path/to/book.epub');

$result->markdown;      // the whole book, spine order
$result->title;         // "Buku Uji"
$result->authors;       // ["Penulis Uji"]
$result->language;      // "id"
$result->description;   // blurb, plain text (embedded HTML stripped)
$result->publisher;     // "Penerbit Uji"
$result->subjects;      // ["Bisnis"] — dc:subject tags
$result->identifiers;   // ["uji-1"] — ISBN, UUID, whatever the book declares
$result->chapters;      // Chapter[] — href, title, markdown per spine document
$result->outline;       // [['level' => 1, 'title' => 'Bab Satu'], ...]
```

Failures throw `FounderplusLabs\EpubMarkdown\Exceptions\InvalidEpub` with a
human-readable message that is safe to surface in a UI.

### Bring your own converter

If your corpus already speaks a markdown dialect, keep it — one dialect per
corpus beats a prettier stranger:

```php
$epub = new EpubToMarkdown(
    fn (string $chapterHtml): string => MyHtmlToMarkdown::convert($chapterHtml),
);
```

The callable receives each chapter's inner `<body>` HTML and returns markdown.

### Pairs well with league/html-to-markdown

Need a richer dialect — nested lists, tables, code blocks? Plug in
[league/html-to-markdown](https://github.com/thephpleague/html-to-markdown),
the ecosystem standard:

```bash
composer require league/html-to-markdown
```

```php
use League\HTMLToMarkdown\HtmlConverter;

$league = new HtmlConverter(['strip_tags' => true, 'header_style' => 'atx']);

$result = (new EpubToMarkdown(
    fn (string $html): string => $league->convert($html),
))->convert('/path/to/book.epub');
```

The built-in converter stays the default because it is dependency-free and
deterministic — but the seam is there when you want more.

### Laravel

No service provider needed — it is a plain class. Construct it where you
need it, or bind it once:

```php
$this->app->singleton(EpubToMarkdown::class);
```

## Design notes

- **Nothing is extracted to disk.** Entries are read from the zip in memory,
  so zip-slip is structurally impossible.
- **Hostile XML is inert.** The container and OPF are parsed with
  `LIBXML_NONET` and without entity substitution — a malicious book cannot
  reach the network or read local files through its manifest.
- **Nav documents are excluded.** Table-of-contents chrome
  (`properties="nav"`) never pollutes the content.
- **The default converter is deliberately lite**: headings, paragraphs,
  lists, blockquotes, emphasis, links, horizontal rules, images-as-alt-text.
  Everything else is stripped to text. EPUB chapter XHTML routinely carries doctypes and HTML
  entities that make strict XML parsing miserable; a regex pass over a tame
  dialect is more robust than a DOM here.

## Test

```bash
composer install
composer test
```

Fixtures are built at runtime (an EPUB is just a zip) — the repo carries no
binaries.

## License

MIT © Founderplus
