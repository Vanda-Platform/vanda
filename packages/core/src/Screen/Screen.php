<?php

declare(strict_types=1);

namespace Vanda\Screen;

use Illuminate\Database\Eloquent\Model;
use ReflectionObject;
use ReflectionProperty;
use Vanda\Layouts\Avatar;
use Vanda\Layouts\Badge;
use Vanda\Navigation\Crumb;
use Vanda\Schema\Node;

/**
 * A screen is one page of the panel, written in PHP only.
 *
 * - `query(...)`: optional. Receives route parameters (models are bound by type),
 *   initialises public properties on the first visit and returns read-only data
 *   for the layout (available through $this->prop('key')).
 * - Public typed properties are the screen state. They survive between requests
 *   in a signed snapshot; non-locked ones can be edited by fields.
 * - Methods with #[Action] are the only ones callable from the UI.
 * - Methods with #[Updated('path')] react to changes of a field.
 */
abstract class Screen
{
    /** @var array<string, mixed> */
    private array $props = [];

    /**
     * @return iterable<Node>
     */
    abstract public function layout(): iterable;

    public function title(): ?string
    {
        return null;
    }

    public function description(): ?string
    {
        return null;
    }

    /**
     * Picture before the title of a record's screen:
     *
     *     public function avatar(): ?Avatar { return Avatar::make($this->user->name); }
     */
    public function avatar(): ?Avatar
    {
        return null;
    }

    /**
     * Status next to the title: `Badge::make('Active', Tone::Success)->dot()`.
     */
    public function badge(): ?Badge
    {
        return null;
    }

    /**
     * Buttons and links in the screen header.
     *
     * @return iterable<Node>
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * Path to this screen. Empty = derived from the active menu item and the title.
     *
     * @return iterable<Crumb>
     */
    public function breadcrumbs(): iterable
    {
        return [];
    }

    /**
     * The panel with the sidebar, or a bare card for sign-in style pages.
     */
    public function chrome(): Chrome
    {
        return Chrome::Panel;
    }

    /**
     * The record a button of this screen works on, for the activity log: the first public
     * property that holds a saved model. Override it when the screen has several.
     */
    public function activitySubject(): ?Model
    {
        foreach ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || ! $property->isInitialized($this)) {
                continue;
            }

            $value = $property->getValue($this);
            if ($value instanceof Model && $value->exists) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Read-only data returned by query().
     */
    final public function prop(string $key, mixed $default = null): mixed
    {
        return data_get($this->props, $key, $default);
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $props
     */
    final public function setProps(array $props): void
    {
        $this->props = $props;
    }
}
