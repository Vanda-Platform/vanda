<?php

namespace App\Vanda\Forms;

use App\Enums\PageStatus;
use Vanda\Attributes\Validate;
use Vanda\Form\Form;

final class PageForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string|max:255|alpha_dash')]
    public string $slug = '';

    #[Validate('nullable|integer|exists:pages,id')]
    public ?int $parent_id = null;

    public PageStatus $status = PageStatus::Draft;

    #[Validate('required|in:page,section,catalog,category,product,blog,home')]
    public string $template = 'page';

    public string $content = '';

    #[Validate('nullable|string|max:20000')]
    public string $notes = '';

    #[Validate('nullable|json')]
    public string $settings = '';

    public ?int $cover = null;

    /** @var list<int> */
    #[Validate('array|max:10')]
    public array $files = [];
}
