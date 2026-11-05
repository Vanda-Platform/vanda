<?php

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Vanda\Attachments\Attachable;

class Page extends Model
{
    use Attachable;

    protected $fillable = ['parent_id', 'position', 'title', 'slug', 'status', 'template', 'content', 'notes', 'settings'];

    protected function casts(): array
    {
        return ['status' => PageStatus::class];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Address of the page on the site, built from the slugs of its ancestors.
     *
     * @param array<int, self> $all every page by id, to avoid a query per level
     */
    public function path(array $all): string
    {
        $slugs = [];
        for ($page = $this; $page !== null; $page = $all[$page->parent_id] ?? null) {
            if ($page->slug !== 'home') {
                array_unshift($slugs, $page->slug);
            }
        }

        return '/'.implode('/', $slugs);
    }

    /**
     * Keys of this page and of every page inside it.
     *
     * @return list<int>
     */
    public function branchKeys(): array
    {
        $keys = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = self::query()->whereIn('parent_id', $level)->pluck('id')->all();
            $keys = [...$keys, ...$level];
        }

        return $keys;
    }
}
