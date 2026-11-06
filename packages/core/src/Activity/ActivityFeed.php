<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Vanda\Access\Authorizer;
use Vanda\Layouts\Avatar;
use Vanda\Navigation\Shell;
use Vanda\Panel;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * Entries of the activity log, newest first: who, when, with which button, and the values before
 * and after. On a record's screen it answers "who changed this last":
 *
 *     Layout::tabs([
 *         Tab::make('История')->schema([Layout::activity($this->post)]),
 *     ])
 */
final class ActivityFeed extends Node
{
    /** Values in a feed are cut to this length; the entry's own page shows them whole. */
    private const SHORT = 280;

    private int $limit = 20;

    private bool $full = false;

    private bool $subjects = false;

    private bool $changesOnly = false;

    /**
     * @param Model|list<Activity> $source a record, or entries already chosen
     */
    private function __construct(private readonly Model|array $from) {}

    /**
     * The history of one record.
     */
    public static function for(Model $record): self
    {
        return (new self($record))->captureSource();
    }

    /**
     * Entries already chosen (one entry's page, entries of one action).
     *
     * @param iterable<Activity> $entries
     */
    public static function entries(iterable $entries): self
    {
        return (new self(array_values(is_array($entries) ? $entries : iterator_to_array($entries))))->captureSource();
    }

    public function type(): string
    {
        return 'activity';
    }

    /**
     * How many entries of a record to show (20); the rest is on the Activity screen.
     */
    public function limit(int $count): self
    {
        $this->limit = max(1, $count);

        return $this;
    }

    /**
     * Whole values instead of the first lines.
     */
    public function full(bool $full = true): self
    {
        $this->full = $full;

        return $this;
    }

    /**
     * Name the record of each entry (for entries of different records).
     */
    public function withSubjects(bool $show = true): self
    {
        $this->subjects = $show;

        return $this;
    }

    /**
     * Only the changed values, without who and when (the entry's own page shows those above).
     */
    public function changesOnly(bool $only = true): self
    {
        $this->changesOnly = $only;

        return $this;
    }

    protected function properties(SchemaContext $context): array
    {
        $more = null;

        if ($this->from instanceof Model) {
            $entries = $this->from->exists
                ? Activity::for($this->from)->with('causer')->limit($this->limit + 1)->get()->all()
                : [];

            if (count($entries) > $this->limit) {
                array_pop($entries);
                $more = self::listUrl(['subject_type' => $this->from->getMorphClass(), 'subject_id' => (string) $this->from->getKey()]);
            }
        } else {
            $entries = $this->from;
        }

        return [
            'entries' => array_map(fn (Activity $entry) => $this->entry($entry), $entries),
            'empty' => __('vanda::activity.empty_record'),
            'changesOnly' => $this->changesOnly ?: null,
            'more' => $more === null ? null : ['label' => __('vanda::activity.all_history'), 'href' => $more],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(Activity $entry): array
    {
        $name = $entry->causerLabel();
        $impersonator = $entry->properties['impersonator'] ?? null;
        $causer = $entry->relationLoaded('causer') ? $entry->causer : null;
        $ids = (array) ($entry->properties['ids'] ?? []);

        return array_filter([
            'id' => $entry->id,
            'event' => $entry->event->value,
            'eventLabel' => $entry->event->label(),
            'tone' => $entry->event->tone()->value,
            'causer' => array_filter([
                'name' => $name,
                'initials' => Avatar::initials($name),
                'hue' => Avatar::hue($name),
                'image' => app(Shell::class)->avatarOf($causer),
                'note' => is_string($impersonator) ? __('vanda::activity.impersonator', ['name' => $impersonator]) : null,
            ], static fn ($value) => $value !== null),
            'at' => $entry->created_at?->toIso8601String(),
            'date' => $entry->created_at?->translatedFormat('j F Y, H:i'),
            'action' => $entry->action,
            'screen' => $entry->screen,
            'note' => $ids === [] ? null : __('vanda::activity.selected', ['count' => count($ids)]),
            'subject' => $this->subjects ? $entry->subjectLabel() : null,
            'subjectHref' => $this->subjects ? $entry->subjectUrl() : null,
            'changes' => array_map(fn (array $change) => $change['secret']
                ? ['label' => $change['label'], 'old' => null, 'new' => null, 'secret' => true]
                : ['label' => $change['label'], 'old' => self::display($change['old'], $this->full), 'new' => self::display($change['new'], $this->full)],
                $entry->changeList()),
            'href' => $this->full ? null : self::entryUrl($entry),
        ], static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * A value as people read it: text without tags, yes/no, cut in feeds.
     */
    public static function display(mixed $value, bool $full = false): ?string
    {
        if (is_bool($value)) {
            return (string) __($value ? 'vanda::activity.yes' : 'vanda::activity.no');
        }
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));

        if ($text !== strip_tags($text)) {
            $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $text)))));
        }

        return $full ? $text : Str::limit($text, self::SHORT);
    }

    /**
     * Address of an entry's page, when ActivityScreen is routed and the user may open it.
     */
    public static function entryUrl(Activity $entry): ?string
    {
        if (! Authorizer::allowsScreenClass(ActivityScreen::class)) {
            return null;
        }

        return rescue(static fn () => Panel::url(ActivityScreen::class, ['activity' => $entry->id]), null, report: false);
    }

    /**
     * Address of the Activity screen with a query, when it is routed and the user may open it.
     *
     * @param array<string, string> $query
     */
    public static function listUrl(array $query = []): ?string
    {
        if (! Authorizer::allowsScreenClass(ActivityListScreen::class)) {
            return null;
        }

        $url = rescue(static fn () => Panel::url(ActivityListScreen::class), null, report: false);

        return $url === null || $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
