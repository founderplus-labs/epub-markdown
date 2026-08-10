<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

use FounderplusLabs\EpubMarkdown\Exceptions\InvalidEpub;

/**
 * Minimal EPUB reader: container.xml → OPF → metadata + spine-ordered
 * chapter documents. Reads entries straight from the zip in memory — nothing
 * is ever extracted to disk, so zip-slip is structurally impossible.
 *
 * XML is parsed with LIBXML_NONET and WITHOUT entity substitution, so a
 * hostile book cannot reach the network or read local files through its
 * manifest.
 */
final class Epub
{
    private const CONTAINER = 'META-INF/container.xml';

    private const NS_CONTAINER = 'urn:oasis:names:tc:opendocument:xmlns:container';

    private const NS_OPF = 'http://www.idpf.org/2007/opf';

    private const NS_DC = 'http://purl.org/dc/elements/1.1/';

    private function __construct(
        private readonly \ZipArchive $zip,
        public readonly ?string $title,
        /** @var list<string> */
        public readonly array $authors,
        public readonly ?string $language,
        /** @var list<string> chapter hrefs, resolved against the zip root, spine order */
        public readonly array $spineHrefs,
    ) {}

    public static function open(string $path): self
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidEpub('Berkas bukan EPUB yang sah (bukan arsip zip).');
        }

        $container = $zip->getFromName(self::CONTAINER);
        if ($container === false) {
            $zip->close();

            throw new InvalidEpub('EPUB tanpa META-INF/container.xml.');
        }

        $opfPath = self::opfPath($container);
        $opfXml = $zip->getFromName($opfPath);
        if ($opfXml === false) {
            $zip->close();

            throw new InvalidEpub("EPUB menunjuk paket {$opfPath} yang tidak ada.");
        }

        $doc = self::xml($opfXml, 'paket OPF');
        $opfDir = str_contains($opfPath, '/') ? dirname($opfPath) . '/' : '';

        // Manifest: id → href, filtered to XHTML documents.
        $manifest = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_OPF, 'item') as $item) {
            $mediaType = strtolower((string) $item->getAttribute('media-type'));
            if (!in_array($mediaType, ['application/xhtml+xml', 'text/html'], true)) {
                continue;
            }
            // Nav/TOC documents are chrome, not content.
            if (str_contains((string) $item->getAttribute('properties'), 'nav')) {
                continue;
            }
            $manifest[(string) $item->getAttribute('id')] = (string) $item->getAttribute('href');
        }

        // Spine: reading order.
        $spineHrefs = [];
        foreach ($doc->getElementsByTagNameNS(self::NS_OPF, 'itemref') as $ref) {
            $idref = (string) $ref->getAttribute('idref');
            if (isset($manifest[$idref])) {
                $spineHrefs[] = self::resolve($opfDir, $manifest[$idref]);
            }
        }

        if ($spineHrefs === []) {
            $zip->close();

            throw new InvalidEpub('EPUB tanpa satu pun dokumen isi di spine.');
        }

        $dc = fn (string $tag): array => array_map(
            fn (\DOMElement $el) => trim($el->textContent),
            iterator_to_array($doc->getElementsByTagNameNS(self::NS_DC, $tag)),
        );

        return new self(
            zip: $zip,
            title: $dc('title')[0] ?? null,
            authors: array_values(array_filter($dc('creator'))),
            language: $dc('language')[0] ?? null,
            spineHrefs: $spineHrefs,
        );
    }

    public function read(string $href): ?string
    {
        $content = $this->zip->getFromName($href);

        return $content === false ? null : $content;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    private static function opfPath(string $containerXml): string
    {
        $doc = self::xml($containerXml, 'container.xml');

        foreach ($doc->getElementsByTagNameNS(self::NS_CONTAINER, 'rootfile') as $rootfile) {
            $fullPath = trim((string) $rootfile->getAttribute('full-path'));
            if ($fullPath !== '') {
                return $fullPath;
            }
        }

        throw new InvalidEpub('container.xml tanpa rootfile OPF.');
    }

    private static function xml(string $xml, string $label): \DOMDocument
    {
        $doc = new \DOMDocument();
        // NONET: no network. No NOENT: entities are never substituted.
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new InvalidEpub("EPUB dengan {$label} yang tidak bisa diurai.");
        }

        return $doc;
    }

    /** Resolve an OPF-relative href (with ../ segments and %-encoding) to a zip path. */
    private static function resolve(string $opfDir, string $href): string
    {
        $href = rawurldecode(explode('#', $href, 2)[0]);
        $path = $opfDir . $href;

        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
