<?php

declare(strict_types=1);

use Vanda\Panel;
use Vanda\Tests\Fixtures\FieldsScreen;
use Vanda\Tests\Fixtures\User;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\VandaException;

beforeEach(function () {
    Illuminate\Support\Facades\View::addLocation(__DIR__.'/Fixtures/views');   // FieldsScreen's Blade island
    $this->user = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
});

it('refuses to set a path no field of the screen edits, and says what was meant', function () {
    Panel::test(UserEditScreen::class, ['user' => $this->user])->set('form.emial', 'x@example.com');
})->throws(VandaException::class, 'Panel::test(UserEditScreen::class)->set(\'form.emial\', …): no field of the screen edits "form.emial", so the browser could never send it and the test would pass on a value that was ignored. Did you mean "form.email"? Fields of the screen: form.name, form.email');

it('refuses to set a read-only field, and points at tamper() for a test of the server', function () {
    Panel::test(FieldsScreen::class)->set('number', 'hacked');
})->throws(VandaException::class, 'the field "number" is read-only, disabled or hidden on this screen, so the browser never sends it');

it('sets a field and a place inside one, such as a Repeater row, as the browser does', function () {
    Panel::test(UserEditScreen::class, ['user' => $this->user])
        ->set('form.email', 'anna.new@example.com')
        ->assertSet('form.email', 'anna.new@example.com');

    Panel::test(Vanda\Tests\Fixtures\ShowcaseScreen::class)
        ->set('form.specs', [['name' => 'Weight', 'value' => '1 kg']])
        ->set('form.specs.0.value', '2 kg')
        ->assertSet('form.specs.0.value', '2 kg');
});
