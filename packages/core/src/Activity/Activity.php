<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One entry of the activity log. Names of the user and the record are kept as they were,
 * so the log still reads well after either is deleted.
 *
 *     Activity::for($post)->first()?->causer_name   // who changed the post last
 *
 * Old entries are removed by `php artisan model:prune --model="Vanda\Activity\Activity"`
 * after config('vanda.activity.keep_days') days (365; null keeps everything).
 *
 * @property int $id
 * @property ActivityEvent $event
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $causer_name
 * @property string|null $screen
 * @property string|null $action
 * @property string|null $url
 * @property array<string, array{0: mixed, 1: mixed}>|null $diff values before and after, by attribute
 * @property array<string, mixed>|null $properties
 * @property string|null $batch
 * @property string|null $ip
 * @property Carbon|null $created_at
 */
final class Activity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'vanda_activity';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event' => ActivityEvent::class,
            'diff' => 'array',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Entries about one record, newest first.
     *
     * @return Builder<self>
     */
    public static function for(Model $record): Builder
    {
        return self::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', (string) $record->getKey())
            ->latest('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function causerLabel(): string
    {
        return $this->causer_name ?? (string) __('vanda::activity.system');
    }

    /**
     * What the record is called in the log: «Новость «Летняя акция»».
     */
    public function subjectLabel(): ?string
    {
        if ($this->subject_type === null) {
            return null;
        }

        $type = $this->subjectType();

        return $this->subject_label !== null ? "{$type} «{$this->subject_label}»" : $type;
    }

    public function subjectType(): ?string
    {
        if ($this->subject_type === null) {
            return null;
        }

        $class = Model::getActualClassNameForMorph($this->subject_type);

        return is_subclass_of($class, Model::class) && method_exists($class, 'activityOptions')
            ? (new $class)->activityOptions()->typeName($class)
            : Str::headline(class_basename($class));
    }

    /**
     * Address of the record's screen, when its model names one (ActivityOptions::screen())
     * and the record still exists.
     */
    public function subjectUrl(): ?string
    {
        $subject = $this->relationLoaded('subject') ? $this->subject : rescue(fn () => $this->subject, null, report: false);

        return $subject instanceof Model && method_exists($subject, 'activityOptions')
            ? $subject->activityOptions()->urlOf($subject)
            : null;
    }

    /**
     * The changed attributes with their labels; secret ones without values.
     *
     * @return list<array{attribute: string, label: string, old: mixed, new: mixed, secret: bool}>
     */
    public function changeList(): array
    {
        $labels = (array) ($this->properties['labels'] ?? []);
        $secret = (array) ($this->properties['secret'] ?? []);
        $list = [];

        foreach ($this->diff ?? [] as $attribute => $values) {
            $list[] = [
                'attribute' => (string) $attribute,
                'label' => (string) ($labels[$attribute] ?? Str::headline((string) $attribute)),
                'old' => $values[0] ?? null,
                'new' => $values[1] ?? null,
                'secret' => in_array($attribute, $secret, true),
            ];
        }

        return $list;
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $days = config('vanda.activity.keep_days', 365);

        return $days === null
            ? self::query()->whereRaw('1 = 0')
            : self::query()->where('created_at', '<', now()->subDays((int) $days));
    }
}
