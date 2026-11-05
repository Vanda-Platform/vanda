<?php

declare(strict_types=1);

namespace Vanda\Schema;

use JsonSerializable;

/**
 * A small, side-effect-free expression evaluated in the browser without a
 * round-trip (visibleWhen, requiredWhen, computed values) and on the server
 * with exactly the same rules, so validation always matches what the user saw.
 *
 * Serialized form: {"field": "form.country"}, {"value": 42} or {"op": "in", "args": [...]}.
 * The renderer implements the same operations in packages/renderer/src/expr.ts;
 * packages/schema/fixtures/expressions.json keeps both sides in sync.
 */
final class Expr implements JsonSerializable
{
    public const OPERATORS = [
        'eq', 'neq', 'in', 'gt', 'gte', 'lt', 'lte', 'empty', 'notEmpty',
        'and', 'or', 'not', 'slug', 'lower', 'concat',
    ];

    /**
     * @param array<string, mixed> $node
     */
    private function __construct(private readonly array $node) {}

    public static function field(string $path): self
    {
        return new self(['field' => $path]);
    }

    public static function value(mixed $value): self
    {
        return new self(['value' => $value]);
    }

    public static function all(self ...$expressions): self
    {
        return self::op('and', ...$expressions);
    }

    public static function any(self ...$expressions): self
    {
        return self::op('or', ...$expressions);
    }

    public static function concat(self|string|int|float ...$parts): self
    {
        return self::op('concat', ...array_map(self::wrap(...), $parts));
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function fromArray(array $node): self
    {
        return new self($node);
    }

    public function eq(mixed $value): self
    {
        return self::op('eq', $this, self::wrap($value));
    }

    public function neq(mixed $value): self
    {
        return self::op('neq', $this, self::wrap($value));
    }

    /**
     * @param list<mixed> $values
     */
    public function in(array $values): self
    {
        return self::op('in', $this, self::value(array_values($values)));
    }

    public function gt(mixed $value): self
    {
        return self::op('gt', $this, self::wrap($value));
    }

    public function gte(mixed $value): self
    {
        return self::op('gte', $this, self::wrap($value));
    }

    public function lt(mixed $value): self
    {
        return self::op('lt', $this, self::wrap($value));
    }

    public function lte(mixed $value): self
    {
        return self::op('lte', $this, self::wrap($value));
    }

    public function isEmpty(): self
    {
        return self::op('empty', $this);
    }

    public function isNotEmpty(): self
    {
        return self::op('notEmpty', $this);
    }

    public function not(): self
    {
        return self::op('not', $this);
    }

    public function and(self $other): self
    {
        return self::op('and', $this, $other);
    }

    public function or(self $other): self
    {
        return self::op('or', $this, $other);
    }

    /** "Как мы обжариваем кофе" → "kak-my-obzharivaem-kofe". */
    public function slug(): self
    {
        return self::op('slug', $this);
    }

    public function lower(): self
    {
        return self::op('lower', $this);
    }

    /**
     * State paths this expression reads; used to know which fields affect it.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        $found = [];
        $walk = static function (array $node) use (&$walk, &$found): void {
            if (isset($node['field'])) {
                $found[] = $node['field'];
            }
            foreach ($node['args'] ?? [] as $arg) {
                $walk($arg instanceof self ? $arg->toArray() : $arg);
            }
        };
        $walk($this->toArray());

        return array_values(array_unique($found));
    }

    /**
     * @param array<string, mixed> $state
     */
    public function evaluate(array $state): mixed
    {
        return self::run($this->toArray(), $state);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function isTrue(array $state): bool
    {
        return self::truthy($this->evaluate($state));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! isset($this->node['args'])) {
            return $this->node;
        }

        return [
            'op' => $this->node['op'],
            'args' => array_map(
                static fn (mixed $arg) => $arg instanceof self ? $arg->toArray() : $arg,
                $this->node['args'],
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function op(string $op, self ...$args): self
    {
        return new self(['op' => $op, 'args' => $args]);
    }

    private static function wrap(mixed $value): self
    {
        return $value instanceof self ? $value : self::value($value);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $state
     */
    private static function run(array $node, array $state): mixed
    {
        if (array_key_exists('field', $node)) {
            return data_get($state, $node['field']);
        }
        if (array_key_exists('value', $node)) {
            return $node['value'];
        }

        $args = $node['args'] ?? [];
        $value = static fn (int $i) => self::run($args[$i] ?? ['value' => null], $state);

        return match ($node['op'] ?? null) {
            'eq' => self::equals($value(0), $value(1)),
            'neq' => ! self::equals($value(0), $value(1)),
            'in' => self::contains((array) $value(1), $value(0)),
            'gt' => self::compare($value(0), $value(1), static fn ($a, $b) => $a > $b),
            'gte' => self::compare($value(0), $value(1), static fn ($a, $b) => $a >= $b),
            'lt' => self::compare($value(0), $value(1), static fn ($a, $b) => $a < $b),
            'lte' => self::compare($value(0), $value(1), static fn ($a, $b) => $a <= $b),
            'empty' => self::blank($value(0)),
            'notEmpty' => ! self::blank($value(0)),
            'and' => array_reduce(array_keys($args), static fn (bool $carry, int $i) => $carry && self::truthy($value($i)), true),
            'or' => array_reduce(array_keys($args), static fn (bool $carry, int $i) => $carry || self::truthy($value($i)), false),
            'not' => ! self::truthy($value(0)),
            'slug' => self::slugify((string) ($value(0) ?? '')),
            'lower' => mb_strtolower((string) ($value(0) ?? '')),
            'concat' => implode('', array_map(static fn (int $i) => (string) ($value($i) ?? ''), array_keys($args))),
            default => null,
        };
    }

    public static function truthy(mixed $value): bool
    {
        return ! ($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0 || $value === []);
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    /** Numbers and numeric strings compare as numbers: "12" equals 12. */
    private static function normalize(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return (float) $value;
        }

        return is_int($value) ? (float) $value : $value;
    }

    private static function equals(mixed $a, mixed $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    /**
     * @param array<mixed> $list
     */
    private static function contains(array $list, mixed $needle): bool
    {
        foreach ($list as $item) {
            if (self::equals($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $a, mixed $b, callable $test): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        return is_float($a) && is_float($b) && $test($a, $b);
    }

    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    private static function slugify(string $text): string
    {
        $text = strtr(mb_strtolower($text), self::TRANSLIT);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }
}
