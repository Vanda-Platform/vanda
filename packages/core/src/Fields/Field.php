<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Closure;
use Vanda\Schema\Expr;
use Vanda\Schema\Node;
use Vanda\Schema\SchemaContext;

/**
 * A field edits one value of the screen state, addressed by a dot path:
 * `Input::make('form.email')` edits `$this->form->email`.
 */
abstract class Field extends Node
{
    protected ?string $label = null;

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected bool $required = false;

    protected bool $disabled = false;

    protected bool $readonly = false;

    /** @var string|list<mixed>|Closure|null */
    protected string|array|Closure|null $rules = null;

    /** Debounce in milliseconds for server reactions; null = react only via dependsOn. */
    protected ?int $live = null;

    protected bool $liveValidation = false;

    protected ?Expr $requiredWhen = null;

    protected ?Expr $disabledWhen = null;

    protected ?Expr $computed = null;

    final public function __construct(protected readonly string $path) {}

    public static function make(string $path): static
    {
        return (new static($path))->captureSource();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function getId(): ?string
    {
        return $this->id ?? $this->path;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /** Marks the field as required in the UI. Add the `required` rule to validate it. */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    /**
     * Validation rules for a field that is not backed by a Form object.
     * Fields of a Form are validated by the Form's own rules.
     *
     * @param string|list<mixed>|Closure(): (string|list<mixed>) $rules
     */
    public function rules(string|array|Closure $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return list<mixed>
     */
    public function getRules(): array
    {
        $rules = $this->rules instanceof Closure ? ($this->rules)() : $this->rules;

        return match (true) {
            $rules === null => [],
            is_string($rules) => explode('|', $rules),
            default => array_values($rules),
        };
    }

    /** Send changes to the server (after the debounce) so #[Updated] hooks and dependents run. */
    public function live(int $debounce = 250): static
    {
        $this->live = $debounce;

        return $this;
    }

    /** Validate this field on the server as the user types. */
    public function liveValidation(bool $enabled = true): static
    {
        $this->liveValidation = $enabled;
        $this->live ??= 400;

        return $this;
    }

    public function hasLiveValidation(): bool
    {
        return $this->liveValidation;
    }

    public function requiredWhen(Expr $condition): static
    {
        $this->requiredWhen = $condition;

        return $this;
    }

    public function getRequiredWhen(): ?Expr
    {
        return $this->requiredWhen;
    }

    public function disabledWhen(Expr $condition): static
    {
        $this->disabledWhen = $condition;

        return $this;
    }

    /** Value is calculated in the browser from other fields, e.g. a slug from a title. */
    public function computed(Expr $value): static
    {
        $this->computed = $value;

        return $this;
    }

    public function isEditable(): bool
    {
        return ! $this->disabled && ! $this->readonly;
    }

    /**
     * Cleans the value that came from the browser before it is applied to state.
     * Structured fields (Repeater, KeyValue, permissions) drop unknown keys here.
     *
     * @param mixed $current the value on the server before this request
     */
    public function sanitize(mixed $value, mixed $current = null): mixed
    {
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function properties(SchemaContext $context): array
    {
        return [
            'path' => $this->path,
            'label' => $this->label,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'readonly' => $this->readonly,
            'live' => $this->live,
            'liveValidation' => $this->liveValidation,
            'requiredWhen' => $this->requiredWhen,
            'disabledWhen' => $this->disabledWhen,
            'computed' => $this->computed,
            ...$this->fieldProperties($context),
        ];
    }

    /**
     * Extra properties of a concrete field.
     *
     * @return array<string, mixed>
     */
    protected function fieldProperties(SchemaContext $context): array
    {
        return [];
    }
}
