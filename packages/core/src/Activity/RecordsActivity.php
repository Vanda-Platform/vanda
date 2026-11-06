<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Puts the model's changes into the activity log: who created, changed, deleted or restored
 * a record, on which screen and with which button, with the values before and after.
 *
 *     class Post extends Model
 *     {
 *         use RecordsActivity;
 *
 *         public function activityOptions(): ActivityOptions
 *         {
 *             return ActivityOptions::make('Новость')->screen(PostEditScreen::class, 'post');
 *         }
 *     }
 *
 * Only changes made by the buttons of Vanda screens are recorded (see ActivityOptions::everywhere()).
 * Mass updates and deletes through the query builder fire no model events and are not seen
 * as changes; the button that ran them is still recorded as an action.
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(static fn (self $model) => app(ActivityLog::class)->recordModel(ActivityEvent::Created, $model));
        static::updated(static fn (self $model) => app(ActivityLog::class)->recordModel(ActivityEvent::Updated, $model));
        static::deleted(static fn (self $model) => app(ActivityLog::class)->recordModel(ActivityEvent::Deleted, $model));

        if (method_exists(static::class, 'restored')) {
            static::restored(static fn (self $model) => app(ActivityLog::class)->recordModel(ActivityEvent::Restored, $model));
        }
    }

    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make();
    }

    /**
     * Entries about this record, newest first.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('id');
    }
}
