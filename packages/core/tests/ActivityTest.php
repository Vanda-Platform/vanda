<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Vanda\Access\Impersonation;
use Vanda\Activity\Activity;
use Vanda\Activity\ActivityContext;
use Vanda\Activity\ActivityEvent;
use Vanda\Activity\ActivityLog;
use Vanda\Activity\ActivityOptions;
use Vanda\Activity\RecordsActivity;
use Vanda\Panel;
use Vanda\Tests\Fixtures\Article;
use Vanda\Tests\Fixtures\ArticleScreen;
use Vanda\Tests\Fixtures\ArticleStatus;
use Vanda\Tests\Fixtures\User;
use Vanda\VandaException;

final class ArticleRecordedEverywhere extends Model
{
    use RecordsActivity;

    protected $table = 'articles';

    protected $guarded = [];

    public function activityOptions(): ActivityOptions
    {
        return ActivityOptions::make('Статья')->everywhere();
    }
}

beforeEach(function () {
    $this->vera = User::query()->create(['name' => 'Vera Mironova', 'email' => 'vera@example.com', 'password' => Hash::make('Tq8#zLm2!vR'), 'permissions' => ['platform.activity' => true]]);
    $this->anna = User::query()->create(['name' => 'Anna Smirnova', 'email' => 'anna@example.com']);
    $this->article = Article::query()->create(['title' => 'Летняя акция', 'body' => '<p>Скидки</p>'])->fresh();
    $this->actingAs($this->vera);
});

/** Runs code as one press of a button on a screen. */
function inPanel(Closure $call, string $action = 'Сохранить'): mixed
{
    return app(ActivityLog::class)->during(new ActivityContext('Статьи', $action, '/admin/articles', batch: 'b6c0a7a6-2f0e-4d43-8f43-5f1c3c1a1a11'), $call);
}

/** The first node of a type in a page's IR. */
function nodeOfType(array $nodes, string $type): ?array
{
    foreach ($nodes as $node) {
        if (($node['type'] ?? null) === $type) {
            return $node;
        }
        if ($found = nodeOfType($node['children'] ?? [], $type)) {
            return $found;
        }
    }

    return null;
}

it('records who changed a record, on which screen, with which button, and the values', function () {
    Panel::test(ArticleScreen::class, ['article' => $this->article])
        ->set('form.title', 'Осенняя акция')
        ->set('form.published', true)
        ->set('form.token', 'new-secret')
        ->set('form.views', 99)
        ->call('save', from: 'action.save')
        ->assertToast('Сохранено');

    $entry = Activity::for($this->article)->sole();

    expect($entry->event)->toBe(ActivityEvent::Updated)
        ->and($entry->causer_name)->toBe('Vera Mironova')
        ->and($entry->causer_id)->toBe((string) $this->vera->id)
        ->and($entry->screen)->toBe('Статья')
        ->and($entry->action)->toBe('Сохранить')
        ->and($entry->subject_label)->toBe('Осенняя акция')
        ->and($entry->subjectLabel())->toBe('Статья «Осенняя акция»')
        ->and($entry->subjectUrl())->toBe(url('/admin/articles/'.$this->article->id))
        // the hidden token changed without its values; views are left out by the model's options
        ->and($entry->diff)->toBe(['title' => ['Летняя акция', 'Осенняя акция'], 'published' => [false, true], 'token' => [null, null]])
        ->and($entry->properties)->toBe(['labels' => ['title' => 'Заголовок', 'published' => 'Опубликована', 'token' => 'Токен'], 'secret' => ['token']]);
});

it('records a button that changed nothing, but not the loading of a modal', function () {
    Panel::test(ArticleScreen::class, ['article' => $this->article])
        ->call('prepare', from: 'prepare')
        ->call('send', from: 'action.send')
        ->call('save', from: 'action.save');

    // «Сохранить» without changes is still a press of the button
    expect(Activity::query()->orderBy('id')->pluck('action')->all())->toBe(['Отправить в 1С', 'Сохранить']);

    $entry = Activity::query()->orderBy('id')->first();
    expect($entry->event)->toBe(ActivityEvent::Action)
        ->and($entry->action)->toBe('Отправить в 1С')
        ->and($entry->subject_id)->toBe((string) $this->article->id)
        ->and($entry->subject_label)->toBe('Летняя акция');
});

it('keeps the values of new and deleted records, one batch per button press', function () {
    $article = inPanel(fn () => Article::query()->create(['title' => 'Новая', 'body' => '<p>Текст</p>']), 'Создать');
    inPanel(fn () => $article->delete(), 'Удалить');

    [$created, $deleted] = Activity::for($article)->reorder('id')->get()->all();

    expect($created->event)->toBe(ActivityEvent::Created)
        ->and($created->diff)->toBe(['title' => [null, 'Новая'], 'body' => [null, '<p>Текст</p>']])
        ->and($created->batch)->toBe('b6c0a7a6-2f0e-4d43-8f43-5f1c3c1a1a11')
        ->and($deleted->event)->toBe(ActivityEvent::Deleted)
        ->and($deleted->diff['title'])->toBe(['Новая', null])
        ->and($deleted->subjectUrl())->toBeNull();
});

it('does not count an empty text that became null as a change', function () {
    $this->article->forceFill(['body' => ''])->saveQuietly();

    inPanel(fn () => $this->article->update(['body' => null, 'title' => 'Новое название']));

    expect(Activity::for($this->article)->sole()->diff)->toBe(['title' => ['Летняя акция', 'Новое название']]);
});

it('keeps values of enums by their labels', function () {
    inPanel(fn () => $this->article->update(['status' => ArticleStatus::Draft]));
    inPanel(fn () => $this->article->update(['status' => ArticleStatus::Published]));

    expect(Activity::for($this->article)->pluck('diff')->all())->toBe([
        ['status' => ['Черновик', 'Опубликована']],
        ['status' => [null, 'Черновик']],
    ]);
});

it('leaves out changes made outside the panel unless the model asks for them', function () {
    $this->article->update(['title' => 'Из консоли']);
    expect(Activity::query()->count())->toBe(0);

    ArticleRecordedEverywhere::query()->create(['title' => 'Из очереди']);
    expect(Activity::query()->sole())
        ->event->toBe(ActivityEvent::Created)
        ->screen->toBeNull()
        ->subject_label->toBe('Из очереди');
});

it('records nothing while the log is turned off', function () {
    config(['vanda.activity.enabled' => false]);

    inPanel(fn () => $this->article->update(['title' => 'Тихо']));
    Panel::test(ArticleScreen::class, ['article' => $this->article])->call('send', from: 'action.send');

    expect(Activity::query()->count())->toBe(0);
});

it('explains how to create the table when it is missing', function () {
    Schema::drop('vanda_activity');

    inPanel(fn () => $this->article->update(['title' => 'Без таблицы']));
})->throws(VandaException::class, 'php artisan migrate');

it('shows the history of a record on its screen, newest first', function () {
    foreach (['Первая', 'Вторая', 'Третья'] as $title) {
        inPanel(fn () => $this->article->update(['title' => $title]));
    }

    $feed = nodeOfType(Panel::test(ArticleScreen::class, ['article' => $this->article])->page()['layout'], 'activity');
    $last = Activity::for($this->article)->first();

    expect($feed['entries'])->toHaveCount(2)
        ->and($feed['entries'][0])->toMatchArray([
            'id' => $last->id,
            'event' => 'updated',
            'eventLabel' => 'Updated',
            'tone' => 'accent',
            'action' => 'Сохранить',
            'screen' => 'Статьи',
            'href' => url('/admin/activity/'.$last->id),
            'changes' => [['label' => 'Title', 'old' => 'Вторая', 'new' => 'Третья']],
        ])
        ->and($feed['entries'][0]['causer'])->toMatchArray(['name' => 'Vera Mironova', 'initials' => 'VM'])
        ->and($feed['more']['href'])->toBe(url('/admin/activity').'?'.http_build_query(['subject_type' => Article::class, 'subject_id' => (string) $this->article->id]));
});

it('shows rich text as plain text and cuts long values in the feed', function () {
    inPanel(fn () => $this->article->update(['body' => '<p>Новый <b>текст</b></p><p>'.str_repeat('а', 400).'</p>']));

    $change = nodeOfType(Panel::test(ArticleScreen::class, ['article' => $this->article])->page()['layout'], 'activity')['entries'][0]['changes'][0];

    expect($change['old'])->toBe('Скидки')
        ->and($change['new'])->toStartWith('Новый текст ааа')
        ->and(mb_strlen($change['new']))->toBeLessThan(300);
});

it('marks changes made while signed in as another user', function () {
    inPanel(fn () => Impersonation::loginAs($this->anna), 'Войти под пользователем');
    inPanel(fn () => $this->article->update(['title' => 'От имени Анны']));

    [$impersonated, $updated] = Activity::query()->orderBy('id')->get()->all();

    expect($impersonated)
        ->event->toBe(ActivityEvent::Impersonated)
        ->causer_name->toBe('Vera Mironova')
        ->subject_label->toBe('Anna Smirnova')
        ->and($updated)
        ->causer_name->toBe('Anna Smirnova')
        ->properties->toMatchArray(['impersonator' => 'Vera Mironova']);
});

it('records signing in and out of the panel', function () {
    auth()->logout();

    signInAs('vera@example.com', 'Tq8#zLm2!vR');
    $this->post('/panel/logout')->assertRedirect();

    expect(Activity::query()->orderBy('id')->pluck('event')->all())->toBe([ActivityEvent::Login, ActivityEvent::Logout])
        ->and(Activity::query()->pluck('causer_name')->unique()->all())->toBe(['Vera Mironova']);
});

it('lists the activity to users with the permission, with the history of one record', function () {
    inPanel(fn () => $this->article->update(['title' => 'Осенняя акция']));
    inPanel(fn () => null, 'Отправить в 1С');

    $page = $this->getJson('/admin/activity', ['X-Vanda' => 'true'])->assertOk()->json();
    $table = $page['layout'][0];
    $entry = Activity::for($this->article)->first();

    expect($page['title'])->toBe('Activity')
        ->and($table['rows'])->toHaveCount(2)
        ->and($table['rows'][1]['link'])->toBe(url('/admin/activity/'.$entry->id))
        ->and($table['rows'][1]['cells']['subject_label']['text'])->toBe('Статья «Осенняя акция»')
        ->and($table['rows'][1]['cells']['details']['text'])->toBe('Fields: Title')
        ->and($table['rows'][0]['cells']['details']['text'])->toBe('Button «Отправить в 1С»')
        ->and(collect($table['views'])->pluck('count')->all())->toBe([2, 1, 1, 0]);

    $history = $this->getJson('/admin/activity?'.http_build_query(['subject_type' => Article::class, 'subject_id' => $this->article->id]), ['X-Vanda' => 'true'])->json();
    expect($history['title'])->toBe('History of Статья «Осенняя акция»')
        ->and($history['layout'][0]['rows'])->toHaveCount(1);

    $this->actingAs($this->anna)->get('/admin/activity')->assertForbidden();
});

it('opens one entry with whole values and the other changes of the same button press', function () {
    inPanel(function () {
        $this->article->update(['title' => 'Осенняя акция']);
        Article::query()->create(['title' => 'Вторая статья']);
    });
    $entry = Activity::for($this->article)->first();

    $page = $this->getJson('/admin/activity/'.$entry->id, ['X-Vanda' => 'true'])->assertOk()->json();

    expect($page['title'])->toBe('Updated: Статья «Осенняя акция»')
        ->and(nodeOfType($page['layout'], 'legend'))->not->toBeNull()
        ->and(collect($page['commandBar'])->pluck('label')->all())->toBe(['Full history of the record', 'Open the record']);

    $cards = array_values(array_filter($page['layout'], static fn (array $node) => $node['type'] === 'card'));
    expect($cards[0]['children'][0])->toMatchArray(['type' => 'activity', 'changesOnly' => true])
        ->and($cards[1]['children'][0]['entries'][0]['subject'])->toBe('Статья «Вторая статья»');
});

it('prunes entries older than the configured number of days', function () {
    inPanel(fn () => $this->article->update(['title' => 'Старое']));
    inPanel(fn () => $this->article->update(['title' => 'Новое']));
    Activity::query()->orderBy('id')->first()->forceFill(['created_at' => now()->subDays(400)])->save();

    $this->artisan('model:prune', ['--model' => [Activity::class]])->assertSuccessful();

    expect(Activity::query()->pluck('subject_label')->all())->toBe(['Новое']);
});
