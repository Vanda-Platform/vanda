<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Vanda\Actions\Link;
use Vanda\Attributes\Can;
use Vanda\Attributes\Locked;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Filter;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Persona;
use Vanda\Layouts\TD;
use Vanda\Layouts\Text;
use Vanda\Layouts\View;
use Vanda\Navigation\Shell;
use Vanda\Screen\Screen;

/**
 * The activity log: every change and button of the panel, newest first, with filters by user,
 * section, event and date. `?subject_type=…&subject_id=…` shows the history of one record.
 *
 *     Route::screen('activity', ActivityListScreen::class)->name('activity');
 *     Route::screen('activity/{activity}', ActivityScreen::class)->name('activity.show');
 *
 *     MenuItem::make('Активность')->icon('activity')->to(ActivityListScreen::class)
 *     PermissionGroup::make('Система')->add('platform.activity', 'Активность')
 */
#[Can('platform.activity')]
final class ActivityListScreen extends Screen
{
    #[Locked]
    public ?string $subjectType = null;

    #[Locked]
    public ?string $subjectId = null;

    public function query(Request $request): array
    {
        $type = $request->query('subject_type');
        $id = $request->query('subject_id');

        if (is_string($type) && is_string($id) && $type !== '' && $id !== '') {
            $this->subjectType = $type;
            $this->subjectId = $id;
        }

        return [];
    }

    public function title(): string
    {
        $record = $this->recordLabel();

        return $record === null ? (string) __('vanda::activity.title') : (string) __('vanda::activity.record_history', ['record' => $record]);
    }

    public function description(): string
    {
        return (string) __('vanda::activity.description');
    }

    public function commandBar(): iterable
    {
        if ($this->subjectType === null) {
            return [];
        }

        $url = $this->latest()?->subjectUrl();

        return [
            Link::make(__('vanda::activity.show_everything'))->icon('list')->to(self::class),
            Link::make(__('vanda::activity.open_record'))->icon('external')->href((string) $url)->visible($url !== null),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::table()
                ->query(fn () => Activity::query()->with('causer')->when(
                    $this->subjectType !== null,
                    fn (Builder $query) => $query->where('subject_type', $this->subjectType)->where('subject_id', $this->subjectId),
                ))
                ->searchable(['subject_label', 'causer_name', 'action', 'screen'], __('vanda::activity.search'))
                ->views([
                    View::make(__('vanda::activity.views.all')),
                    View::make(__('vanda::activity.views.changes'), ['kind' => 'changes']),
                    View::make(__('vanda::activity.views.actions'), ['kind' => 'actions']),
                    View::make(__('vanda::activity.views.sessions'), ['kind' => 'sessions']),
                ])
                ->filters([
                    Filter::select('causer', __('vanda::activity.filters.causer'))
                        ->options(fn () => Activity::query()->whereNotNull('causer_id')->select(['causer_id', 'causer_name'])->distinct()
                            ->orderBy('causer_name')->get()->unique('causer_id')->pluck('causer_name', 'causer_id'))
                        ->apply(fn (Builder $query, string $id) => $query->where('causer_id', $id)),
                    Filter::select('type', __('vanda::activity.filters.type'))
                        ->options(fn () => Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')
                            ->mapWithKeys(fn (string $type) => [$type => (new Activity(['subject_type' => $type]))->subjectType()])
                            ->sort())
                        ->apply(fn (Builder $query, string $type) => $query->where('subject_type', $type)),
                    Filter::select('event', __('vanda::activity.filters.event'))->options(ActivityEvent::class),
                    Filter::select('kind', __('vanda::activity.filters.kind'))
                        ->options([
                            'changes' => __('vanda::activity.views.changes'),
                            'actions' => __('vanda::activity.views.actions'),
                            'sessions' => __('vanda::activity.views.sessions'),
                        ])
                        ->apply(fn (Builder $query, string $kind) => $query->whereIn('event', array_map(
                            static fn (ActivityEvent $event) => $event->value,
                            match ($kind) {
                                'changes' => ActivityEvent::changes(),
                                'sessions' => ActivityEvent::signIns(),
                                default => [ActivityEvent::Action],
                            },
                        ))),
                    Filter::dateRange('date', __('vanda::activity.filters.date'))->column('created_at'),
                ])
                ->defaultSort('id', 'desc')
                ->paginate(25, [50, 100])
                ->rowLink(fn (Activity $entry) => ActivityFeed::entryUrl($entry))
                ->emptyText(__('vanda::activity.empty'))
                ->columns([
                    TD::make('causer_name', __('vanda::activity.columns.who'))->render(fn (Activity $entry) => $this->causer($entry)),
                    TD::make('event', __('vanda::activity.columns.event'))->render(
                        fn (Activity $entry) => Badge::make($entry->event->label(), $entry->event->tone())->dot(),
                    ),
                    TD::make('subject_label', __('vanda::activity.columns.record'))->render(fn (Activity $entry) => $entry->subjectLabel() ?? '—'),
                    TD::make('details', __('vanda::activity.columns.details'))->hideOnMobile()->render(fn (Activity $entry) => Text::make(self::details($entry))->muted()),
                    TD::make('created_at', __('vanda::activity.columns.when'))->sortable()->muted()->render(
                        fn (Activity $entry) => $entry->created_at?->translatedFormat('j M Y, H:i'),
                    ),
                    TD::make('screen', __('vanda::activity.columns.screen'))->defaultHidden()->hideOnMobile()->render(fn (Activity $entry) => $entry->screen ?? '—'),
                ]),
        ];
    }

    /**
     * «Поля: Заголовок, Текст и ещё 2» or «Кнопка «Отправить в 1С»».
     */
    public static function details(Activity $entry): string
    {
        $labels = array_column($entry->changeList(), 'label');

        if ($labels !== [] && in_array($entry->event, [ActivityEvent::Updated, ActivityEvent::Restored], true)) {
            $shown = array_slice($labels, 0, 4);
            $rest = count($labels) - count($shown);

            return (string) __('vanda::activity.fields', ['fields' => implode(', ', $shown).($rest > 0 ? " +{$rest}" : '')]);
        }

        if ($entry->action !== null) {
            $ids = (array) ($entry->properties['ids'] ?? []);

            return __('vanda::activity.button', ['action' => $entry->action]).($ids === [] ? '' : ' · '.__('vanda::activity.selected', ['count' => count($ids)]));
        }

        return $entry->screen ?? '—';
    }

    private function causer(Activity $entry): Persona
    {
        $impersonator = $entry->properties['impersonator'] ?? null;

        return Persona::make($entry->causerLabel())
            ->subtitle(is_string($impersonator) ? (string) __('vanda::activity.impersonator', ['name' => $impersonator]) : null)
            ->image(app(Shell::class)->avatarOf($entry->causer));
    }

    private function latest(): ?Activity
    {
        return $this->subjectType === null ? null : Activity::query()
            ->where('subject_type', $this->subjectType)->where('subject_id', $this->subjectId)
            ->latest('id')->first();
    }

    private function recordLabel(): ?string
    {
        if ($this->subjectType === null) {
            return null;
        }

        $label = $this->latest()?->subjectLabel();
        $class = Model::getActualClassNameForMorph($this->subjectType);

        return $label ?? Str::headline(class_basename($class)).' #'.$this->subjectId;
    }
}
