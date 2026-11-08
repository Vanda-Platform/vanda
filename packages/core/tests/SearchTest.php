<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Vanda\Support\Search;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    User::query()->create(['name' => 'Анна Смирнова', 'email' => 'anna@example.com', 'country' => 'RU']);
    User::query()->create(['name' => 'Ölaf Groß', 'email' => 'OLAF@Example.com', 'country' => 'DE']);
    User::query()->create(['name' => 'snake_case 100%', 'email' => 'snake@example.com', 'country' => 'FR']);
});

function found(string $text, array $columns = ['name']): array
{
    return Search::apply(User::query(), $columns, $text)->orderBy('id')->pluck('name')->all();
}

it('finds text whatever its case in any alphabet', function () {
    expect(found('анна'))->toBe(['Анна Смирнова'])
        ->and(found('СМИРН'))->toBe(['Анна Смирнова'])
        ->and(found('öLAF'))->toBe(['Ölaf Groß'])
        ->and(found('example.COM', ['email']))->toHaveCount(3);
});

it('matches % and _ as themselves', function () {
    expect(found('_'))->toBe(['snake_case 100%'])
        ->and(found('0%'))->toBe(['snake_case 100%'])
        ->and(found('%'))->toBe(['snake_case 100%']);
});

it('looks in every column given and leaves the query alone for an empty text', function () {
    expect(found('olaf@', ['name', 'email']))->toBe(['Ölaf Groß'])
        ->and(found('   '))->toHaveCount(3);
});

it('adds a condition to a group of your own and works on a plain query builder', function () {
    $names = User::query()
        ->where(function ($where) {
            Search::orContains($where, 'name', 'АННА');
            $where->orWhere('country', 'FR');
        })
        ->orderBy('id')->pluck('name')->all();

    expect($names)->toBe(['Анна Смирнова', 'snake_case 100%'])
        ->and(Search::apply(DB::table('users'), ['name'], 'СМИРНОВА')->count())->toBe(1);
});
