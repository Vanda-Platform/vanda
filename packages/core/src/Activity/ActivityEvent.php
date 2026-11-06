<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Vanda\Layouts\Tone;

/**
 * What an activity entry is about.
 */
enum ActivityEvent: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    /** A button of a screen that changed no recorded model, or ActivityLog::note(). */
    case Action = 'action';
    case Login = 'login';
    case Logout = 'logout';
    case Impersonated = 'impersonated';

    public function label(): string
    {
        return (string) __('vanda::activity.events.'.$this->value);
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Created, self::Restored => Tone::Success,
            self::Updated => Tone::Accent,
            self::Deleted => Tone::Danger,
            self::Impersonated => Tone::Warning,
            default => Tone::Neutral,
        };
    }

    /**
     * Events that change a record (the "Changes" view of the list).
     *
     * @return list<self>
     */
    public static function changes(): array
    {
        return [self::Created, self::Updated, self::Deleted, self::Restored];
    }

    /**
     * @return list<self>
     */
    public static function signIns(): array
    {
        return [self::Login, self::Logout, self::Impersonated];
    }
}
