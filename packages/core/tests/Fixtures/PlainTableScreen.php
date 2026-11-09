<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Screen;

/** A table that forgets its view (->remember(false)). */
final class PlainTableScreen extends Screen
{
    public function layout(): iterable
    {
        return [
            Layout::table()->query(fn () => User::query())->searchable(['name'])->paginate(2)->columns([TD::make('name', 'Name')])->remember(false),
        ];
    }
}
