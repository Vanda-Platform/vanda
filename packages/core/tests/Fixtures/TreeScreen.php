<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Vanda\Attributes\Action;
use Vanda\Fields\Select;
use Vanda\Layouts\Layout;
use Vanda\Layouts\TD;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;

final class TreeScreen extends Screen
{
    public const PAGES = [
        ['id' => 1, 'title' => 'Home', 'parent_id' => null, 'position' => 0],
        ['id' => 2, 'title' => 'About', 'parent_id' => null, 'position' => 1],
        ['id' => 3, 'title' => 'Team', 'parent_id' => 2, 'position' => 1],
        ['id' => 4, 'title' => 'History', 'parent_id' => 2, 'position' => 0],
        ['id' => 5, 'title' => 'People', 'parent_id' => 3, 'position' => 0],
        ['id' => 6, 'title' => 'Orphan', 'parent_id' => 99, 'position' => 2],
    ];

    /** @var list<array<string, mixed>> */
    public static array $moves = [];

    public ?int $parent = null;

    /** @var list<int> */
    public array $sections = [];

    public function layout(): iterable
    {
        return [
            Layout::tree()
                ->items(self::PAGES)
                ->orderBy('position')
                ->columns([TD::make('title', 'Title')])
                ->reorderable($this->move(...)),
            Select::make('parent')->tree(self::PAGES, label: 'title')->excludeBranch(3),
            Select::make('sections')->tree(self::PAGES, label: 'title')->multiple(),
        ];
    }

    #[Action(validate: false)]
    public function move(int $id, ?int $parent, int $position): Result
    {
        self::$moves[] = compact('id', 'parent', 'position');

        return Result::toast('Moved');
    }
}
