<?php

declare(strict_types=1);

namespace Vanda\Attachments;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Files of a model through Orchid's `attachmentable` table:
 *
 *     class Page extends Model { use Attachable; }
 *
 *     $page->attachments('cover')->first()?->url();
 *     $page->syncAttachments($this->form->gallery, 'gallery');   // ids from an Upload field
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
     * Makes the files of a group exactly these, in this order.
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
