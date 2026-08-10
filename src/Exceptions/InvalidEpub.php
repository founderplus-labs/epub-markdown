<?php

declare(strict_types=1);

namespace FounderplusLabs\EpubMarkdown\Exceptions;

/**
 * The file is not a readable EPUB. The message is written for humans and is
 * safe to surface directly in a UI.
 */
final class InvalidEpub extends \RuntimeException {}
