<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Illuminate\Database\Eloquent\Model;

/**
 * Where a change happens: the screen, the button and the record of one action call.
 * Every entry written during the call carries it and shares its batch id.
 */
final class ActivityContext
{
    /**
     * @param array<string, string> $labels attribute => label of the screen's fields, e.g. ['title' => 'Заголовок']
     * @param array<string, mixed> $properties stored with the entry of a call that changed nothing (e.g. selected ids)
     * @param bool $recordsCall write an entry for the call itself when it changed no recorded model
     */
    public function __construct(
        public readonly ?string $screen,
        public readonly ?string $action,
        public readonly ?string $url,
        public readonly ?Model $subject = null,
        public readonly array $labels = [],
        public readonly array $properties = [],
        public readonly bool $recordsCall = true,
        public readonly string $batch = '',
    ) {}
}
