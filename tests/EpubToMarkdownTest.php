<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown\Tests;

use FounderplusLabs\EpubMarkdown\EpubToMarkdown;
use FounderplusLabs\EpubMarkdown\Exceptions\InvalidEpub;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures are BUILT here — an EPUB is just a zip — so the repo carries no
 * binaries and every structural claim (spine order, ../ resolution, nav
 * exclusion) is spelled out in the test that depends on it.
 */
final class EpubToMarkdownTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_spine_order_metadata_and_outline(): void
    {
        $path = $this->buildEpub(
            chapters: [
                'ch2.xhtml' => '<html><body><h1>Bab Dua</h1><p>Isi kedua.</p></body></html>',
                'ch1.xhtml' => '<html><body><h1>Bab Satu</h1><p>Isi <strong>pertama</strong>.</p></body></html>',
            ],
            // Spine deliberately reverses the manifest declaration order.
            spineIds: ['c-ch1', 'c-ch2'],
        );

        $result = (new EpubToMarkdown())->convert($path);

        $this->assertSame('Buku Uji', $result->title);
        $this->assertSame(['Penulis Uji'], $result->authors);
        $this->assertSame('id', $result->language);
        // Spine order wins over manifest order.
        $this->assertMatchesRegularExpression('/Bab Satu.*Bab Dua/s', $result->markdown);
        $this->assertStringContainsString('# Bab Satu', $result->markdown);
        $this->assertStringContainsString('Isi **pertama**.', $result->markdown);
        $this->assertSame(
            [['level' => 1, 'title' => 'Bab Satu'], ['level' => 1, 'title' => 'Bab Dua']],
            $result->outline,
        );
        $this->assertCount(2, $result->chapters);
        $this->assertSame('Bab Satu', $result->chapters[0]->title);
    }

    public function test_nav_document_is_excluded_from_content(): void
    {
        $path = $this->buildEpub(
            chapters: ['ch1.xhtml' => '<html><body><p>Isi asli.</p></body></html>'],
            spineIds: ['c-ch1', 'nav'],
            extraManifest: '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>',
            extraFiles: ['OEBPS/nav.xhtml' => '<html><body><ol><li>Daftar isi palsu</li></ol></body></html>'],
        );

        $result = (new EpubToMarkdown())->convert($path);

        $this->assertStringContainsString('Isi asli.', $result->markdown);
        $this->assertStringNotContainsString('Daftar isi palsu', $result->markdown);
    }

    public function test_custom_converter_is_used(): void
    {
        $path = $this->buildEpub(
            chapters: ['ch1.xhtml' => '<html><body><p>Apa saja.</p></body></html>'],
            spineIds: ['c-ch1'],
        );

        $result = (new EpubToMarkdown(fn (string $html): string => 'DIALEK-SENDIRI'))->convert($path);

        $this->assertSame('DIALEK-SENDIRI', $result->markdown);
    }

    public function test_not_a_zip_fails_readably(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epub') . '.epub';
        $this->paths[] = $path;
        file_put_contents($path, 'bukan zip');

        $this->expectException(InvalidEpub::class);
        (new EpubToMarkdown())->convert($path);
    }

    public function test_zip_without_container_fails_readably(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epub') . '.epub';
        $this->paths[] = $path;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->close();

        $this->expectException(InvalidEpub::class);
        (new EpubToMarkdown())->convert($path);
    }

    /**
     * @param array<string, string> $chapters   basename → xhtml (stored under OEBPS/)
     * @param list<string>          $spineIds
     * @param array<string, string> $extraFiles full zip path → content
     */
    private function buildEpub(array $chapters, array $spineIds, string $extraManifest = '', array $extraFiles = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub') . '.epub';
        $this->paths[] = $path;

        $manifest = $extraManifest;
        foreach ($chapters as $name => $_) {
            $manifest .= sprintf(
                '<item id="c-%s" href="%s" media-type="application/xhtml+xml"/>',
                pathinfo($name, PATHINFO_FILENAME),
                $name,
            );
        }

        $spine = implode('', array_map(fn (string $id) => "<itemref idref=\"{$id}\"/>", $spineIds));

        $opf = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid">
              <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                <dc:identifier id="uid">uji-1</dc:identifier>
                <dc:title>Buku Uji</dc:title>
                <dc:creator>Penulis Uji</dc:creator>
                <dc:language>id</dc:language>
              </metadata>
              <manifest>{$manifest}</manifest>
              <spine>{$spine}</spine>
            </package>
            XML;

        $container = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
              <rootfiles>
                <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
              </rootfiles>
            </container>
            XML;

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', $container);
        $zip->addFromString('OEBPS/content.opf', $opf);
        foreach ($chapters as $name => $xhtml) {
            $zip->addFromString("OEBPS/{$name}", $xhtml);
        }
        foreach ($extraFiles as $zipPath => $content) {
            $zip->addFromString($zipPath, $content);
        }
        $zip->close();

        return $path;
    }
}
