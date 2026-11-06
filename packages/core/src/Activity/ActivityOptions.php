<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Vanda\Panel;
use Vanda\Screen\Screen;

/**
 * How a model with RecordsActivity appears in the activity log:
 *
 *     public function activityOptions(): ActivityOptions
 *     {
 *         return ActivityOptions::make('Новость')
 *             ->title('title')
 *             ->screen(PostEditScreen::class, 'post')
 *             ->except('views_count');
 *     }
 */
final class ActivityOptions
{
    /** Never interesting: keys, timestamps and tokens. */
    private const IGNORED = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];

    /** Stored as "changed" without the values, whatever the model hides. */
    private const SECRET = ['password', 'remember_token', 'api_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    private string|Closure|null $title = null;

    /** @var list<string> */
    private array $except = [];

    /** @var list<string>|null */
    private ?array $only = null;

    /** @var list<string> */
    private array $secret = [];

    /** @var array<string, string> */
    private array $labels = [];

    /** @var class-string<Screen>|null */
    private ?string $screen = null;

    private ?string $parameter = null;

    private bool $everywhere = false;

    private function __construct(private readonly ?string $type) {}

    /**
     * @param string|null $type what one record is called: «Новость», «Заявка» (the class name by default)
     */
    public static function make(?string $type = null): self
    {
        return new self($type);
    }

    /**
     * The name of a record in the log: an attribute, or a closure for anything longer.
     * By default the first of title, name, label, email that is filled, else «#id».
     *
     * @param string|Closure(Model): string $title
     */
    public function title(string|Closure $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Attributes whose changes are not worth an entry (counters, caches).
     */
    public function except(string ...$attributes): self
    {
        $this->except = array_values(array_merge($this->except, $attributes));

        return $this;
    }

    /**
     * Record only these attributes.
     */
    public function only(string ...$attributes): self
    {
        $this->only = array_values($attributes);

        return $this;
    }

    /**
     * Attributes recorded as "changed" without their values (tokens, keys); passwords, remember
     * tokens and the model's $hidden attributes are always kept that way.
     */
    public function secret(string ...$attributes): self
    {
        $this->secret = array_values(array_merge($this->secret, $attributes));

        return $this;
    }

    /**
     * Names of attributes in the log. Without them the labels of the screen's fields are used
     * (`Input::make('post.title')->label('Заголовок')` names `title`), else the attribute itself.
     *
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        $this->labels = $labels + $this->labels;

        return $this;
    }

    /**
     * The screen that opens a record, so the log links to it:
     * `->screen(PostEditScreen::class, 'post')` for the route `posts/{post}/edit`;
     * without a parameter for a screen of one record (settings, a banner).
     *
     * @param class-string<Screen> $screen
     * @param string|null $parameter route parameter that takes the record
     */
    public function screen(string $screen, ?string $parameter = null): self
    {
        $this->screen = $screen;
        $this->parameter = $parameter;

        return $this;
    }

    /**
     * Record changes made outside the panel too: the site, queued jobs, console commands.
     * By default only changes made by the buttons of Vanda screens are recorded.
     */
    public function everywhere(bool $everywhere = true): self
    {
        $this->everywhere = $everywhere;

        return $this;
    }

    public function recordsEverywhere(): bool
    {
        return $this->everywhere;
    }

    /**
     * @param class-string<Model>|Model $model
     */
    public function typeName(string|Model $model): string
    {
        return $this->type ?? Str::headline(class_basename($model));
    }

    public function titleOf(Model $model): string
    {
        $title = match (true) {
            $this->title instanceof Closure => ($this->title)($model),
            is_string($this->title) => data_get($model, $this->title),
            default => collect(['title', 'name', 'label', 'email'])
                ->map(static fn (string $attribute) => $model->getAttribute($attribute))
                ->first(static fn (mixed $value) => is_scalar($value) && trim((string) $value) !== ''),
        };

        $title = is_scalar($title) ? trim(strip_tags((string) $title)) : '';

        return Str::limit($title !== '' ? $title : '#'.$model->getKey(), 180);
    }

    public function tracks(string $attribute): bool
    {
        return ! in_array($attribute, self::IGNORED, true)
            && ! in_array($attribute, $this->except, true)
            && ($this->only === null || in_array($attribute, $this->only, true));
    }

    public function isSecret(Model $model, string $attribute): bool
    {
        return in_array($attribute, self::SECRET, true)
            || in_array($attribute, $this->secret, true)
            || in_array($attribute, $model->getHidden(), true);
    }

    public function labelOf(string $attribute): ?string
    {
        return $this->labels[$attribute] ?? null;
    }

    /**
     * Address of the record's screen, when ->screen() is set and the route exists.
     */
    public function urlOf(Model $model): ?string
    {
        if ($this->screen === null || ! $model->exists) {
            return null;
        }

        return rescue(fn () => Panel::url($this->screen, $this->parameter === null ? [] : [$this->parameter => $model]), null, report: false);
    }
}
