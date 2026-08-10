<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown\Tests;

use FounderplusLabs\EpubMarkdown\HtmlConverter;
use PHPUnit\Framework\TestCase;

final class HtmlConverterTest extends TestCase
{
    private HtmlConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new HtmlConverter();
    }

    public function test_whitespace_between_inline_tags_stays_a_word_boundary(): void
    {
        $md = $this->converter->convert('<p>Kata <em>satu</em> <strong>dua</strong> tiga.</p>');

        $this->assertSame('Kata *satu* **dua** tiga.', $md);
    }

    public function test_adjacent_paragraphs_do_not_glue_words(): void
    {
        $md = $this->converter->convert('<p>Akhir kalimat.</p><p>Awal kalimat.</p>');

        $this->assertSame("Akhir kalimat.\n\nAwal kalimat.", $md);
    }

    public function test_every_heading_level_converts(): void
    {
        $html = implode('', array_map(
            fn (int $i) => "<h{$i}>Judul {$i}</h{$i}>",
            range(1, 6),
        ));

        $md = $this->converter->convert($html);

        foreach (range(1, 6) as $i) {
            $this->assertStringContainsString("\n" . str_repeat('#', $i) . " Judul {$i}", "\n{$md}");
        }
    }

    public function test_heading_with_inline_markup_stays_on_one_line(): void
    {
        $md = $this->converter->convert("<h2>Judul <em>miring</em><br/>lanjutan</h2>");

        $this->assertSame('## Judul miring lanjutan', $md);
    }

    public function test_blockquote_gets_quote_prefix_on_every_line(): void
    {
        $md = $this->converter->convert('<blockquote>Baris satu.<br/>Baris dua.</blockquote>');

        $this->assertSame("> Baris satu.\n> Baris dua.", $md);
    }

    public function test_list_after_paragraph_is_separated(): void
    {
        $md = $this->converter->convert('<p>Daftar:</p><ul><li>apel</li><li>jeruk</li></ul><p>Selesai.</p>');

        $this->assertSame("Daftar:\n\n- apel\n- jeruk\n\nSelesai.", $md);
    }

    public function test_link_keeps_url_in_single_or_double_quotes(): void
    {
        $md = $this->converter->convert(
            "<p><a href=\"https://a.test\">ganda</a> dan <a href='https://b.test'>tunggal</a></p>",
        );

        $this->assertSame('[ganda](https://a.test) dan [tunggal](https://b.test)', $md);
    }

    public function test_image_becomes_alt_text_and_empty_alt_vanishes(): void
    {
        $md = $this->converter->convert('<p>Lihat:</p><img src="a.png" alt="Diagram alur"/><img src="b.png" alt=""/>');

        $this->assertSame("Lihat:\n\n*[Gambar: Diagram alur]*", $md);
    }

    public function test_horizontal_rule_converts(): void
    {
        $md = $this->converter->convert('<p>Sebelum.</p><hr class="break"/><p>Sesudah.</p>');

        $this->assertSame("Sebelum.\n\n---\n\nSesudah.", $md);
    }

    public function test_entities_and_nbsp_decode_to_plain_text(): void
    {
        $md = $this->converter->convert('<p>Roti&nbsp;&amp;&nbsp;kopi &mdash; enak.</p>');

        $this->assertSame('Roti & kopi — enak.', $md);
    }

    public function test_source_indentation_never_becomes_a_code_block(): void
    {
        $md = $this->converter->convert("<div>\n    <p>Teks berindentasi di sumber.</p>\n</div>");

        $this->assertSame('Teks berindentasi di sumber.', $md);
    }
}
