<?php

declare(strict_types=1);

namespace Vanda\Form;

use ReflectionClass;
use ReflectionProperty;
use Vanda\Attributes\Secret;
use Vanda\Attributes\Validate;
use Vanda\Screen\Screen;
use Vanda\State\StateCaster;

/**
 * Typed data of a form with its validation rules in one place:
 *
 *     final class UserForm extends Form
 *     {
 *         #[Validate('required|email')]
 *         public string $email = '';
 *
 *         // Optional dynamic rules; parameters are injected, the screen by its class.
 *         public function rules(UserEditScreen $screen): array
 *         {
 *             return ['email' => [Rule::unique('users')->ignore($screen->user)]];
 *         }
 *     }
 *
 * Fields bind to it by path: Input::make('form.email') for `public UserForm $form`.
 */
abstract class Form
{
    final public function __construct() {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): static
    {
        return (new static)->fill($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fill(array $data): static
    {
        foreach (self::properties() as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                $current = $property->isInitialized($this) ? $property->getValue($this) : null;
                $property->setValue($this, StateCaster::hydrate($property->getType(), $data[$name], $current));
            }
        }

        return $this;
    }

    /**
     * @param bool $withSecrets include #[Secret] properties (Vanda leaves them out of page state)
     * @return array<string, mixed>
     */
    public function toArray(bool $withSecrets = true): array
    {
        $data = [];

        foreach (self::properties() as $property) {
            if (! $property->isInitialized($this) || (! $withSecrets && $property->getAttributes(Secret::class) !== [])) {
                continue;
            }

            $data[$property->getName()] = StateCaster::dehydrate($property->getValue($this), $withSecrets);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /**
     * @return array<string, mixed>
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /**
     * Rules from #[Validate] attributes merged with the optional rules() method.
     *
     * @return array<string, list<mixed>>
     */
    final public function validationRules(Screen $screen): array
    {
        $rules = [];

        foreach (self::properties() as $property) {
            foreach ($property->getAttributes(Validate::class) as $attribute) {
                $declared = $attribute->newInstance()->rules;
                $rules[$property->getName()] = is_string($declared) ? explode('|', $declared) : array_values($declared);
            }
        }

        if (method_exists($this, 'rules')) {
            $dynamic = app()->call([$this, 'rules'], [$screen::class => $screen, 'screen' => $screen]);

            foreach ((array) $dynamic as $key => $value) {
                $rules[$key] = [...($rules[$key] ?? []), ...(is_string($value) ? explode('|', $value) : (array) $value)];
            }
        }

        return $rules;
    }

    /**
     * @return list<ReflectionProperty>
     */
    private static function properties(): array
    {
        return array_values(array_filter(
            (new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC),
            static fn (ReflectionProperty $property) => ! $property->isStatic(),
        ));
    }
}
