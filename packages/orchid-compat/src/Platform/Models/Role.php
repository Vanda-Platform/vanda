<?php

declare(strict_types=1);

namespace Orchid\Platform\Models;

use Vanda\Access\Role as VandaRole;

/**
 * Orchid's Role is Vanda's: the same `roles` and `role_users` tables.
 */
class Role extends VandaRole {}
