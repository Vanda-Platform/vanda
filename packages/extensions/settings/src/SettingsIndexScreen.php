<?php

declare(strict_types=1);

namespace Vanda\Settings;

use Vanda\Access\Authorizer;
use Vanda\Actions\Link;
use Vanda\Layouts\ActionRow;
use Vanda\Layouts\EmptyState;
use Vanda\Layouts\Layout;
use Vanda\Screen\Screen;

/**
 * «Настройки»: the pages registered with SettingsExtension::page() that the user may open, each
 * with its description. Pages forbidden by their #[Can] are left out.
 */
final class SettingsIndexScreen extends Screen
{
    public function title(): string
    {
        return (string) __('vanda-settings::settings.title');
    }

    public function description(): string
    {
        return (string) __('vanda-settings::settings.description');
    }

    public function layout(): iterable
    {
        $rows = [];

        foreach (SettingsExtension::registered()->pages() as $page) {
            if (! Authorizer::allowsScreenClass($page)) {
                continue;
            }

            $screen = app($page);
            $rows[] = ActionRow::make((string) $screen->title())
                ->description($screen->description())
                ->buttons([Link::make((string) __('vanda-settings::settings.open'))->icon('arrow-right')->to($page)]);
        }

        if ($rows === []) {
            return [
                EmptyState::make((string) __('vanda-settings::settings.empty'))
                    ->icon('settings')
                    ->description((string) __('vanda-settings::settings.empty_description')),
            ];
        }

        return [Layout::section((string) __('vanda-settings::settings.sections'))->schema([Layout::actions($rows)])];
    }
}
