<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

/** What a conversion hands back. */
final class ConversionResult
{
    /**
     * @param list<Chapter> $chapters
     * @param list<string>  $authors
     * @param list<string>  $subjects
     * @param list<string>  $identifiers
     * @param list<array{level:int, title:string}> $outline
     */
    public function __construct(
        public readonly string $markdown,
        public readonly ?string $title,
        public readonly array $authors,
        public readonly ?string $language,
        public readonly ?string $description,
        public readonly ?string $publisher,
        public readonly array $subjects,
        public readonly array $identifiers,
        public readonly array $chapters,
        public readonly array $outline,
    ) {}
}
