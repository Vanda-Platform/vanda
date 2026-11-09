<?php

declare(strict_types=1);

namespace Vanda\Layouts\Concerns;

use Closure;
use Vanda\Actions\Button;
use Vanda\Actions\Link;
use Vanda\Actions\ModalToggle;
use Vanda\Layouts\TreeTable;
use Vanda\Schema\SchemaContext;
use Vanda\VandaException;

/**
 * What a click on a row of a table or a tree does: open a page (->rowLink()) or run a node built
 * for the row (->rowAction()). One or the other: a row cannot do both.
 */
trait OpensRows
{
    private ?Closure $rowLink = null;

    private ?Closure $rowAction = null;

    /**
     * A click on a row opens this address (Cmd/Ctrl-click: in a new tab); the row's name in the
     * first column becomes the link.
     *
     *     ->rowLink(fn (User $user) => Panel::url(UserEditScreen::class, ['user' => $user]))
     *
     * @param Closure(mixed $row): ?string $link
     */
    public function rowLink(Closure $link): static
    {
        if ($this->rowAction !== null) {
            throw VandaException::rowActionWithRowLink($this->layoutName());
        }

        $this->rowLink = $link;

        return $this;
    }

    /**
     * A click anywhere on a row (but on its own controls) runs this node, built for the row: a
     * ModalToggle opens its modal (loaded for the row by ->load()), a Button calls its #[Action]
     * (asking first with ->confirm()), a Link opens its page. The row's name in the first column
     * becomes a button that does the same, so the keyboard reaches it. A row whose node is hidden
     * (the user may not call its action) or that gets null opens nothing.
     *
     *     ->rowAction(fn (Page $page) => ModalToggle::make('SEO')->modal('seo')->load($this->loadSeo(...), ['id' => $page->id]))
     *
     * @param Closure(mixed $row): (ModalToggle|Button|Link|null) $action
     */
    public function rowAction(Closure $action): static
    {
        if ($this->rowLink !== null) {
            throw VandaException::rowActionWithRowLink($this->layoutName());
        }

        $this->rowAction = $action;

        return $this;
    }

    /**
     * The row's `link` and `action` for the IR; the action's arguments are signed for this row.
     *
     * @return array{link: ?string, action: ?array<string, mixed>}
     */
    private function rowOpens(mixed $row, string $key, SchemaContext $context): array
    {
        $action = null;

        if ($this->rowAction !== null) {
            $node = ($this->rowAction)($row);

            if ($node !== null && ! $node instanceof ModalToggle && ! $node instanceof Button && ! $node instanceof Link) {
                throw VandaException::invalidRowAction($this->layoutName(), get_debug_type($node));
            }

            if ($node !== null && $node->isVisible()) {
                $action = $node->id($this->id.'.'.$key.'.action')->toSchema($context);
            }
        }

        return [
            'link' => $this->rowLink === null ? null : ($this->rowLink)($row),
            'action' => $action,
        ];
    }

    private function layoutName(): string
    {
        return $this instanceof TreeTable ? 'Layout::tree()' : 'Layout::table()';
    }
}
