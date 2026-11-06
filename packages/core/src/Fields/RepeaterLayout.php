<?php

declare(strict_types=1);

namespace Vanda\Fields;

/**
 * How a Repeater draws its rows. Without Repeater::layout() it picks one by the fields:
 * one field → List, up to four short fields → Table, anything else → Blocks.
 */
enum RepeaterLayout: string
{
    /** One input per row, the label once above the list. */
    case List = 'list';

    /** Columns with the labels as headers (Orchid's Matrix). Blocks on phones. */
    case Table = 'table';

    /** Each row a block of fields, blocks divided by a line inside the same card. */
    case Blocks = 'blocks';
}
