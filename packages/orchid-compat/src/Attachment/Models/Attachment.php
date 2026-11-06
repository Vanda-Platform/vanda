<?php

declare(strict_types=1);

namespace Orchid\Attachment\Models;

use Vanda\Attachments\Attachment as VandaAttachment;

/**
 * Orchid's Attachment on Vanda's model: the same `attachments` table, plus the
 * helpers Orchid code calls.
 *
 * @property-read string $relativeUrl
 */
class Attachment extends VandaAttachment
{
    protected $appends = ['url', 'relativeUrl'];

    /**
     * Path on the disk: "2026/01/15/hash.pdf".
     */
    public function physicalPath(): string
    {
        return $this->relativePath();
    }

    public function getUrlAttribute(): ?string
    {
        return $this->url();
    }

    public function getRelativeUrlAttribute(): ?string
    {
        $url = $this->url();

        return $url === null ? null : (string) parse_url($url, PHP_URL_PATH);
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->original_name;
    }
}
