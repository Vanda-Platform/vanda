<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Attributes\Action;
use Vanda\Attributes\Can;
use Vanda\Layouts\Filter;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Layouts\View;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class TableScreen extends Screen
{
    public function layout(): iterable
    {
        return [
            Layout::table()
                ->query(fn () => User::query())
                ->searchable(['name'])
                ->filters([
                    Filter::select('country', 'Country')->options(['DE' => 'Germany', 'FR' => 'France']),
                    Filter::text('email', 'Email'),
                    Filter::select('domain', 'Domain')->options(['example.com' => 'Example'])
                        ->apply(fn (Builder $query, string $domain) => $query->where('email', 'like', "%@{$domain}")),
                    Filter::dateRange('created', 'Created')->column('created_at'),
                ])
                ->views([View::make('All'), View::make('Germany', ['country' => 'DE'])])
                ->defaultSort('id')
                ->paginate(2, [5])
                ->columns([TD::make('name', 'Name')->sortable(), TD::make('email', 'Email')->truncate('12rem')->hideable(), TD::make('country', 'Country')->defaultHidden()->hideOnMobile()])
                ->rowActions(fn (User $user) => [
                    Link::make('Edit')->icon('pencil')->href('/users/'.$user->id),
                    Button::make('Purge')->danger()->action($this->purge(...), ['user' => $user]),
                ])
                ->exportable('users.csv'),
        ];
    }

    #[Action(validate: false)]
    #[Can('platform.users.delete')]
    public function purge(User $user): Result
    {
        $user->delete();

        return Result::toast('Purged');
    }
}
