<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Vanda\Access\PermissionRegistry;
use Vanda\Activity\Activity;
use Vanda\Panel;
use Vanda\Screen\Screen;
use Vanda\Settings\SettingsExtension;
use Vanda\Settings\SettingsScreen;
use Vanda\Settings\StoredSettings;
use Vanda\Settings\Tests\Fixtures\MailSettings;
use Vanda\Settings\Tests\Fixtures\MailSettingsScreen;
use Vanda\Settings\Tests\Fixtures\ShopMode;
use Vanda\Settings\Tests\Fixtures\ShopSettings;
use Vanda\Settings\Tests\Fixtures\ShopSettingsScreen;
use Vanda\VandaException;

/** What the next request reads: the store is bound per request. */
function nextRequest(): void
{
    app()->forgetScopedInstances();
}

/** The titles of the rows on «Settings». */
function sectionsOf(array $page): array
{
    $titles = [];
    $walk = static function (array $nodes) use (&$walk, &$titles): void {
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'actionrow') {
                $titles[] = $node['title'];
            }
            $walk($node['children'] ?? []);
        }
    };
    $walk($page['layout']);

    return $titles;
}

it('gives the defaults until something is saved, reading every row with one query', function () {
    DB::enableQueryLog();

    expect(ShopSettings::get()->name)->toBe('Vanda Shop')
        ->and(MailSettings::get()->from)->toBeNull()
        ->and(ShopSettings::get()->perPage)->toBe(20)
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('saves typed values and reads them back on the next request', function () {
    $shop = ShopSettings::get();
    $shop->name = 'Лавка';
    $shop->maintenance = true;
    $shop->mode = ShopMode::Closed;
    $shop->phones = ['+7 900 000-00-00'];
    $shop->save();

    nextRequest();
    $read = ShopSettings::get();

    expect($read->name)->toBe('Лавка')
        ->and($read->maintenance)->toBeTrue()
        ->and($read->mode)->toBe(ShopMode::Closed)
        ->and($read->phones)->toBe(['+7 900 000-00-00'])
        ->and($read->perPage)->toBe(20);
});

it('gives a new object every time, so a change is nothing until it is saved', function () {
    ShopSettings::get()->name = 'Changed in memory';

    expect(ShopSettings::get()->name)->toBe('Vanda Shop');
});

it('keeps the default of a property that no longer accepts the saved value', function () {
    StoredSettings::query()->create(['name' => 'shop', 'values' => ['name' => 'Лавка', 'mode' => 'removed-case']]);

    $read = ShopSettings::get();

    expect($read->name)->toBe('Лавка')->and($read->mode)->toBe(ShopMode::Open);
});

it('gives the defaults before the table is migrated', function () {
    Schema::drop('vanda_settings');

    expect(ShopSettings::get()->name)->toBe('Vanda Shop');
});

it('saves from its page with the rules of the settings class', function () {
    signedIn(['settings.shop']);

    Panel::test(ShopSettingsScreen::class)
        ->assertSet('shop.name', 'Vanda Shop')
        ->set('shop.name', '')
        ->call('save')
        ->assertHasErrors(['shop.name']);

    Panel::test(ShopSettingsScreen::class)
        ->set('shop.name', 'Лавка')
        ->set('shop.maintenance', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertToast('Settings saved');

    nextRequest();
    expect(ShopSettings::get()->name)->toBe('Лавка')->and(ShopSettings::get()->maintenance)->toBeTrue();
});

it('records each value that changed, named by the fields of the page', function () {
    signedIn(['settings.shop']);
    Panel::test(ShopSettingsScreen::class)->set('shop.name', 'Лавка')->call('save');
    Panel::test(ShopSettingsScreen::class)->set('shop.name', 'Лавка у дома')->set('shop.perPage', 50)->call('save');

    $entry = Activity::query()->latest('id')->first();

    expect($entry->subject_label)->toBe('Shop')
        ->and($entry->screen)->toBe('Shop')
        ->and($entry->diff)->toBe(['name' => ['Лавка', 'Лавка у дома'], 'perPage' => [20, 50]])
        ->and($entry->properties['labels'])->toBe(['name' => 'Name', 'perPage' => 'Rows per page']);

    // Enum values by their labels; a save outside of the panel's buttons is not recorded.
    Panel::test(ShopSettingsScreen::class)->set('shop.mode', 'closed')->call('save');
    expect(Activity::query()->latest('id')->first()->diff)->toBe(['mode' => ['Open', 'Closed']]);
    $count = Activity::query()->count();
    $shop = ShopSettings::get();
    $shop->name = 'Из консоли';
    $shop->save();
    expect(Activity::query()->count())->toBe($count);

    $record = StoredSettings::query()->where('name', 'shop')->firstOrFail();
    expect($record->activityOptions()->urlOf($record))->toBe(url('/admin/settings/shop'))
        // The log's list names the type from a row that is not loaded.
        ->and((new StoredSettings)->activityOptions()->typeName(StoredSettings::class))->toBe('Settings');
});

it('encrypts a secret, never sends it to the browser and keeps it when left empty', function () {
    signedIn(['settings.mail']);

    Panel::test(MailSettingsScreen::class)->set('mail.from', 'shop@example.com')->set('mail.smtpPassword', 'p4ss-W0rd')->call('save')->assertHasNoErrors();
    expect(DB::table('vanda_settings')->where('name', 'mail')->value('values'))->not->toContain('p4ss-W0rd');

    $page = $this->getJson('/admin/settings/mail', ['X-Vanda' => 'true'])->assertOk()->json();
    expect(json_encode($page))->not->toContain('p4ss-W0rd');

    Panel::test(MailSettingsScreen::class)->set('mail.from', 'hello@example.com')->call('save');
    $entry = Activity::query()->latest('id')->first();

    nextRequest();
    expect(MailSettings::get()->smtpPassword)->toBe('p4ss-W0rd')
        ->and(MailSettings::get()->from)->toBe('hello@example.com')
        // The secret did not change, so the log does not list it.
        ->and($entry->diff)->toBe(['from' => ['shop@example.com', 'hello@example.com']]);

    Panel::test(MailSettingsScreen::class)->set('mail.smtpPassword', 'n3w-Secret')->call('save');
    $entry = Activity::query()->latest('id')->first();
    expect($entry->diff)->toBe(['smtpPassword' => [null, null]])->and($entry->properties['secret'])->toBe(['smtpPassword']);
});

it('mounts the settings page and each page under settings', function () {
    expect(Route::has('admin.settings.index'))->toBeTrue()
        ->and(Route::has('admin.settings.shop'))->toBeTrue()
        ->and(Panel::url(ShopSettingsScreen::class))->toBe(url('/admin/settings/shop'));

    signedIn(['settings.shop']);
    $page = $this->getJson('/admin/settings/shop', ['X-Vanda' => 'true'])->assertOk()->json();

    expect(array_column($page['breadcrumbs'], 'label'))->toBe(['Settings', 'Shop'])
        ->and($page['commandBar'][0]['label'])->toBe('Save');
});

it('lists the pages the user may open', function () {
    signedIn(['settings.mail']);
    $page = $this->getJson('/admin/settings', ['X-Vanda' => 'true'])->assertOk()->json();
    expect(sectionsOf($page))->toBe(['Mail']);

    signedIn();
    $page = $this->getJson('/admin/settings', ['X-Vanda' => 'true'])->assertOk()->json();
    expect(sectionsOf($page))->toBe([])->and($page['layout'][0]['type'])->toBe('empty');

    $this->getJson('/admin/settings/mail', ['X-Vanda' => 'true'])->assertForbidden();
});

it('names the abilities of its pages in the role form', function () {
    expect(app(PermissionRegistry::class)->labels())->toMatchArray(['settings.shop' => 'Shop', 'settings.mail' => 'Mail']);
});

it('explains a page it cannot take', function (Closure $call, string $message) {
    expect($call)->toThrow(VandaException::class, $message);
})->with([
    'not a settings page' => [fn () => SettingsExtension::make()->page('users', NotSettingsScreen::class), 'NotSettingsScreen does not extend Vanda\Settings\SettingsScreen'],
    'nothing to save' => [fn () => SettingsExtension::make()->page('empty', EmptySettingsScreen::class), 'has no public property typed with a Settings class'],
    'a slug that is no address' => [fn () => SettingsExtension::make()->page('Mail Server', MailSettingsScreen::class), 'lowercase latin letters, digits and dashes'],
]);

final class NotSettingsScreen extends Screen
{
    public function layout(): iterable
    {
        return [];
    }
}

final class EmptySettingsScreen extends SettingsScreen
{
    public function layout(): iterable
    {
        return [];
    }
}
