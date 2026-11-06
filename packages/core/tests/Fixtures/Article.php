<?php

declare(strict_types=1);

namespace Vanda\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;

final class Article extends Model
{
    use RecordsActivity;

    protected $table = 'articles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'status' => ArticleStatus::class];
    }

    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make('Статья')->screen(ArticleScreen::class, 'article')->except('views')->secret('token');
    }
}
