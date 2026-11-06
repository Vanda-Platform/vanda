<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * What an Upload field keeps as its value (Orchid's targetId / targetUrl / targetRelativeUrl).
 */
enum UploadTarget: string
{
    /** The attachment id: 42. */
    case Id = 'id';

    /** The public URL as the disk gives it: "https://example.com/storage/2026/01/15/abc.jpg". */
    case Url = 'url';

    /** The path part of that URL: "/storage/2026/01/15/abc.jpg". */
    case RelativeUrl = 'relativeUrl';
}
