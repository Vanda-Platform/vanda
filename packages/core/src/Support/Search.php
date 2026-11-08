<?php

declare(strict_types=1);

namespace Vanda\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PDO;
use Vanda\VandaException;
use WeakMap;

/**
 * "Contains the text, whatever the case", in any language: «анна» finds «Анна Смирнова».
 * `%` and `_` in the text are matched as themselves.
 *
 *     Search::apply(User::query(), ['name', 'email'], $text)->limit(5)->get();
 *
 *     $query->where(function ($where) use ($text) {          // inside a group of your own
 *         Search::orContains($where, 'title', $text);
 *         $where->orWhere('slug', $text);
 *     });
 *
 * A plain `LIKE` ignores case only for Latin letters on SQLite and never on PostgreSQL. Here
 * PostgreSQL gets `ILIKE`, MySQL keeps `LIKE` (its usual `_ci` collations ignore case), and SQLite
 * compares both sides in lower case through `vanda_fold()`, a function Vanda adds to the connection.
 */
final class Search
{
    /** @var WeakMap<PDO, true>|null connections that already have vanda_fold() */
    private static ?WeakMap $folding = null;

    /**
     * Rows where any of the columns contains the text; an empty text leaves the query as it is.
     * Takes an Eloquent query, a relation (`$user->posts()`) or a plain query builder.
     *
     * @template TQuery of BuilderContract
     *
     * @param TQuery $query
     * @param list<string> $columns database columns
     * @return TQuery
     */
    public static function apply(BuilderContract $query, array $columns, string $text): BuilderContract
    {
        $text = trim($text);
        if ($text === '' || $columns === []) {
            return $query;
        }

        self::base($query)->where(static function (QueryBuilder $where) use ($columns, $text): void {
            foreach ($columns as $column) {
                self::orContains($where, $column, $text);
            }
        });

        return $query;
    }

    /** `AND column contains the text`. */
    public static function contains(BuilderContract $query, string $column, string $text): void
    {
        self::where(self::base($query), $column, $text, 'and');
    }

    /** `OR column contains the text`, for a group of conditions of your own. */
    public static function orContains(BuilderContract $query, string $column, string $text): void
    {
        self::where(self::base($query), $column, $text, 'or');
    }

    private static function where(QueryBuilder $query, string $column, string $text, string $boolean): void
    {
        $pattern = '%'.addcslashes($text, '%_\\').'%';
        $connection = $query->getConnection();

        if ($connection instanceof Connection && $connection->getDriverName() === 'sqlite') {
            self::addFolding($connection);
            // SQLite's LIKE has no escape character unless one is named.
            $query->whereRaw('vanda_fold('.$query->getGrammar()->wrap($column).") like ? escape '\\'", [mb_strtolower($pattern)], $boolean);

            return;
        }

        $query->whereLike($column, $pattern, false, $boolean);
    }

    /** The query builder underneath: conditions added to it apply to the query passed in. */
    private static function base(BuilderContract $query): QueryBuilder
    {
        return match (true) {
            $query instanceof QueryBuilder => $query,
            $query instanceof EloquentBuilder => $query->getQuery(),
            $query instanceof Relation => $query->getBaseQuery(),
            default => throw new VandaException(sprintf(
                'Search cannot add conditions to a %s. Pass an Eloquent query (User::query()), a relation ($user->posts()) or DB::table(...).',
                $query::class,
            )),
        };
    }

    /** SQLite's lower() and LIKE fold only Latin letters; vanda_fold() folds every alphabet. */
    private static function addFolding(Connection $connection): void
    {
        self::$folding ??= new WeakMap();
        $fold = static fn (mixed $value): ?string => $value === null ? null : mb_strtolower((string) $value);

        foreach ([$connection->getReadPdo(), $connection->getPdo()] as $pdo) {
            if (isset(self::$folding[$pdo])) {
                continue;
            }
            self::register($pdo, $fold);
            self::$folding[$pdo] = true;
        }
    }

    private static function register(PDO $pdo, Closure $fold): void
    {
        // PHP 8.4 opens SQLite as Pdo\Sqlite, where sqliteCreateFunction() is deprecated.
        if (class_exists(\Pdo\Sqlite::class) && $pdo instanceof \Pdo\Sqlite) {
            $pdo->createFunction('vanda_fold', $fold, 1, \Pdo\Sqlite::DETERMINISTIC);

            return;
        }

        $pdo->sqliteCreateFunction('vanda_fold', $fold, 1, PDO::SQLITE_DETERMINISTIC);
    }
}
