<?php

declare(strict_types=1);

namespace Orchid\Attachment;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Orchid\Attachment\Models\Attachment;

/**
 * Orchid's Attachable: files of a model through the `attachmentable` table.
 */
trait Attachable
{
    public function attachments(?string $group = null): MorphToMany
    {
        $relation = $this->morphToMany(Attachment::class, 'attachmentable', 'attachmentable', 'attachmentable_id', 'attachment_id')
            ->orderBy('sort');

        return $group === null ? $relation : $relation->where('group', $group);
    }

    /**
     * Orchid's older name for attachments().
     */
    public function attachment(?string $group = null): MorphToMany
    {
        return $this->attachments($group);
    }

    /**
     * Makes the files of a group exactly these ids, in this order (what a Vanda Upload field holds).
     *
     * @param int|list<int>|null $ids
     */
    public function syncAttachments(int|array|null $ids, ?string $group = null): void
    {
        $ids = array_values(array_map('intval', (array) $ids));
        $current = $this->attachments($group)->pluck('attachments.id')->all();

        $this->attachments()->detach(array_diff($current, $ids));
        $this->attachments()->syncWithoutDetaching($ids);

        foreach ($ids as $sort => $id) {
            Attachment::query()->whereKey($id)->update(['sort' => $sort]);
        }
    }
}
