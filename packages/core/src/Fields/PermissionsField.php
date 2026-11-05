<?php

declare(strict_types=1);

namespace Vanda\Fields;

use Vanda\Access\PermissionGroup;
use Vanda\Access\PermissionRegistry;
use Vanda\Access\Permissions;
use Vanda\Schema\SchemaContext;

/**
 * Every registered permission, grouped. The value is a map {"platform.users": true}.
 *
 * For a role each permission is on or off. For a user, ->inherited() turns it into
 * three states: inherit from roles (key absent), allow (true) or deny (false), and
 * shows which roles grant each permission.
 */
final class PermissionsField extends Field
{
    /** @var array<string, list<string>>|null */
    private ?array $inherited = null;

    public function type(): string
    {
        return 'permissions';
    }

    /**
     * @param array<string, list<string>> $sources permission => names of roles that grant it
     */
    public function inherited(array $sources): static
    {
        $this->inherited = $sources;

        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function sanitize(mixed $value, mixed $current = null): array
    {
        return Permissions::clean(is_array($value) ? $value : []);
    }

    protected function fieldProperties(SchemaContext $context): array
    {
        return [
            'tristate' => $this->inherited !== null,
            'groups' => array_map(fn (PermissionGroup $group) => [
                'title' => $group->title(),
                'items' => array_map(
                    fn (string $key, string $label) => array_filter([
                        'key' => $key,
                        'label' => $label,
                        'sources' => $this->inherited[$key] ?? null,
                    ]),
                    array_keys($group->items()),
                    $group->items(),
                ),
            ], app(PermissionRegistry::class)->groups()),
        ];
    }
}
