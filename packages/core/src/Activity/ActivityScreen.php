<?php

declare(strict_types=1);

namespace Vanda\Activity;

use Vanda\Actions\Link;
use Vanda\Attributes\Can;
use Vanda\Layouts\Badge;
use Vanda\Layouts\Layout;
use Vanda\Layouts\Persona;
use Vanda\Layouts\Sight;
use Vanda\Navigation\Crumb;
use Vanda\Navigation\Shell;
use Vanda\Screen\Screen;

/**
 * One entry of the activity log: who, when, where, and every changed value whole;
 * other changes made by the same button press below.
 */
#[Can('platform.activity')]
final class ActivityScreen extends Screen
{
    public Activity $activity;

    public function query(Activity $activity): array
    {
        $this->activity = $activity->load('causer');

        return [];
    }

    public function title(): string
    {
        $subject = $this->activity->subjectLabel();

        return $this->activity->event->label().($subject !== null ? ': '.$subject : '');
    }

    public function description(): string
    {
        return $this->activity->causerLabel().', '.$this->activity->created_at?->translatedFormat('j F Y, H:i');
    }

    public function breadcrumbs(): iterable
    {
        return [Crumb::make(__('vanda::activity.title'))->to(ActivityListScreen::class), Crumb::make($this->title())];
    }

    public function commandBar(): iterable
    {
        $url = $this->activity->subjectUrl();
        $history = $this->activity->subject_type === null ? null : ActivityFeed::listUrl([
            'subject_type' => $this->activity->subject_type,
            'subject_id' => (string) $this->activity->subject_id,
        ]);

        return [
            Link::make(__('vanda::activity.all_history'))->icon('history')->href((string) $history)->visible($history !== null),
            Link::make(__('vanda::activity.open_record'))->icon('external')->href((string) $url)->visible($url !== null),
        ];
    }

    public function layout(): iterable
    {
        $entry = $this->activity;
        $impersonator = $entry->properties['impersonator'] ?? null;
        $ids = (array) ($entry->properties['ids'] ?? []);

        $sights = [
            Sight::make('who', __('vanda::activity.detail.who'))->render(fn (Activity $entry) => Persona::make($entry->causerLabel())
                ->subtitle(is_string($impersonator) ? (string) __('vanda::activity.impersonator', ['name' => $impersonator]) : null)
                ->image(app(Shell::class)->avatarOf($entry->causer))),
            Sight::make('event', __('vanda::activity.detail.event'))->render(fn (Activity $entry) => Badge::make($entry->event->label(), $entry->event->tone())->dot()),
            Sight::make('created_at', __('vanda::activity.detail.when'))->render(fn (Activity $entry) => $entry->created_at?->translatedFormat('j F Y, H:i:s')),
        ];

        if ($entry->subject_type !== null) {
            $sights[] = Sight::make('subject', __('vanda::activity.detail.record'))->render(fn (Activity $entry) => $entry->subjectLabel());
        }
        if ($entry->screen !== null) {
            $sights[] = Sight::make('screen', __('vanda::activity.detail.screen'));
        }
        if ($entry->action !== null) {
            $sights[] = Sight::make('action', __('vanda::activity.detail.button'))->render(
                fn (Activity $entry) => $entry->action.($ids === [] ? '' : ' · '.__('vanda::activity.selected', ['count' => count($ids)])),
            );
        }
        if ($entry->url !== null) {
            $sights[] = Sight::make('url', __('vanda::activity.detail.url'))->mono()->copyable();
        }
        if ($entry->ip !== null) {
            $sights[] = Sight::make('ip', __('vanda::activity.detail.ip'))->mono()->copyable();
        }

        $siblings = $entry->batch === null ? [] : Activity::query()->with('causer')
            ->where('batch', $entry->batch)->whereKeyNot($entry->id)->orderBy('id')->limit(50)->get()->all();

        return [
            Layout::legend($entry, $sights),
            Layout::card(__('vanda::activity.changes'))->schema([ActivityFeed::entries([$entry])->full()->changesOnly()])
                ->visible(($entry->diff ?? []) !== []),
            Layout::card(__('vanda::activity.same_action'))->schema([ActivityFeed::entries($siblings)->withSubjects()])
                ->visible($siblings !== []),
        ];
    }
}
