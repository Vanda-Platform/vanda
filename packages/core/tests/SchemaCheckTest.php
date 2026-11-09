<?php

declare(strict_types=1);

use Vanda\Panel;
use Vanda\Schema\SchemaCheck;
use Vanda\Schema\StatePaths;
use Vanda\Support\Icons;
use Vanda\Support\Suggest;
use Vanda\Tests\Fixtures\BrokenScreen;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\VandaException;

it('stops a page whose paths, modals and icons do not exist, saying where and what was meant', function () {
    try {
        Panel::test(BrokenScreen::class);
        $this->fail('The broken screen was built without a word.');
    } catch (VandaException $exception) {
        $message = $exception->getMessage();
    }

    expect($message)
        ->toContain('BrokenScreen has 7 problems that would fail quietly in the browser')
        // A misspelt property of the form, with the line and the right path.
        ->toMatch('/input "Email" at \S*tests\/Fixtures\/BrokenScreen\.php:\d+ is bound to "form\.emial", but UserForm has no property "emial"\. Did you mean "form\.email"\? UserForm has: name, email, country, city, vat, admin, notes\./')
        // A misspelt property of the screen, and what fields may bind to.
        ->toContain('is bound to "from.name", but BrokenScreen has no public property "from". Did you mean "form.name"? Its public properties: form, status, settings.')
        // Nothing inside an enum.
        ->toContain('is bound to "status.label", but "status" is a single value (ArticleStatus) and has nothing inside it.')
        ->toContain('its ->dependsOn(\'form.contry\') waits for a path that never changes, but UserForm has no property "contry". Did you mean "form.country"?')
        ->toContain('its ->visibleWhen() reads "form.contry"')
        ->toContain('opens the modal "quickEdt", but this screen has no Layout::modal(\'quickEdt\', …). Did you mean "quickEdit"? Modals on this screen: quickEdit.')
        ->toContain('has the icon "trashcan", which the renderer does not draw. Did you mean "trash"?')
        ->toContain("\n\nIcons the renderer draws: activity, ")
        // Arrays and Repeater rows have no declared shape: nothing said about them.
        ->not->toContain('settings.mail.from')
        ->not->toContain('"anything"');
});

it('leaves production pages alone and passes every well-formed screen', function () {
    SchemaCheck::$enabled = false;
    expect(Panel::test(BrokenScreen::class)->page()['layout'])->not->toBeEmpty();

    SchemaCheck::$enabled = true;
    expect(Panel::test(UserEditScreen::class)->page()['layout'])->not->toBeEmpty();
});

it('reads paths from the declared types of the screen and its forms', function () {
    $screen = new BrokenScreen;

    expect(StatePaths::broken($screen, 'form.email'))->toBeNull()
        ->and(StatePaths::broken($screen, 'form.*'))->toBeNull()
        ->and(StatePaths::broken($screen, 'settings.anything.at.all'))->toBeNull()
        ->and(StatePaths::broken($screen, 'form.emial'))->toMatchArray(['at' => 'form', 'name' => 'emial', 'owner' => 'UserForm'])
        ->and(StatePaths::broken($screen, 'form.name.first'))->toMatchArray(['at' => 'form.name', 'owner' => 'string', 'leaf' => true]);
});

it('suggests the name that was probably meant', function () {
    expect(Suggest::closest('emial', ['name', 'email']))->toBe('email')
        ->and(Suggest::closest('Email', ['name', 'email']))->toBe('email')
        ->and(Suggest::closest('trashcan', Icons::names()))->toBe('trash')
        ->and(Suggest::closest('zzz', ['name', 'email']))->toBeNull()
        ->and(Icons::exists('trash'))->toBeTrue()
        ->and(Icons::exists('trashcan'))->toBeFalse();
});
