<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown;

/** One spine document, already converted. */
final class Chapter
{
    public function __construct(
        public readonly string $href,
        public readonly ?string $title,
        public readonly string $markdown,
    ) {}
}
