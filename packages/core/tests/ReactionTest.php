<?php

declare(strict_types=1);

use Vanda\Tests\Fixtures\User;
use Vanda\Tests\Fixtures\UserEditScreen;
use Vanda\Panel;

beforeEach(function () {
    $this->user = User::query()->create(['name' => 'Anna', 'email' => 'anna@example.com', 'country' => 'RU', 'city' => 1]);
    User::query()->create(['name' => 'Igor', 'email' => 'igor@example.com']);
});

it('re-resolves only dependent nodes and runs #[Updated] hooks', function () {
    Panel::test(UserEditScreen::class, ['user' => $this->user])
        ->assertHidden('form.vat')
        ->set('form.country', 'DE')
        ->assertSet('form.city', null)
        ->assertNode('form.city', fn (array $node) => array_column($node['options'], 'label') === ['Berlin', 'Munich'])
        ->assertVisible('form.vat');
});

it('validates live fields as the user types', function () {
    Panel::test(UserEditScreen::class, ['user' => $this->user])
        ->set('form.email', 'not-an-email')
        ->assertHasErrors(['form.email'])
        ->set('form.email', 'igor@example.com')
        ->assertHasErrors(['form.email'])
        ->set('form.email', 'anna.new@example.com')
        ->assertHasNoErrors();
});

it('returns patches over HTTP for a reaction', function () {
    $url = '/admin/users/'.$this->user->id.'/edit';
    $page = $this->getJson($url, ['X-Vanda' => 'true'])->json();
    $state = $page['state'];
    $state['form']['country'] = 'FR';

    $response = $this->postJson($url, ['type' => 'react', 'snapshot' => $page['snapshot'], 'state' => $state])->assertOk()->json();

    expect(array_column($response['patches'], 'path'))->toBe(['/nodes/form.city', '/state/form/city'])
        ->and(array_column($response['patches'][0]['value']['options'], 'label'))->toBe(['Paris'])
        ->and($response['patches'][1]['value'])->toBeNull();
});
