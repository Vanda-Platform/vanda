<?php

declare(strict_types=1);

namespace Vanda\Layouts;

/**
 * How Layout::files() draws folders and files at first. The user may switch with the toolbar
 * above it; the browser remembers that choice for the node.
 */
enum FilesLayout: string
{
    /** Tiles with a picture or an icon of the file type: for images and browsing by eye. */
    case Grid = 'grid';

    /** One row per folder or file, size and date at the end: for long lists of documents. */
    case List = 'list';
}
