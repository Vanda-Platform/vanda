<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * An editor that takes pictures into its text (RichText, TinyMce with ->uploads()). The screen's
 * upload request (type=upload, field=<path>) stores them through the Upload it returns.
 */
interface StoresImages
{
    /**
     * The Upload that stores images for this text, or null when uploads are off.
     */
    public function uploader(): ?Upload;
}
