<?php

declare(strict_types=1);

use Vanda\Panel;
use Vanda\Tests\Fixtures\FieldsScreen;
use Vanda\Tests\Fixtures\User;

beforeEach(function () {
    Illuminate\Support\Facades\View::addLocation(__DIR__.'/Fixtures/views');
    $this->ivan = User::query()->create(['name' => 'Ivan Petrov', 'email' => 'ivan@example.com']);
    $this->anna = User::query()->create(['name' => 'Anna Smirnova', 'email' => 'anna@rocont.dev']);
    $this->oleg = User::query()->create(['name' => 'Oleg Ivanov', 'email' => 'oleg@example.com']);
});

it('finds records for a relation as the user types', function () {
    $screen = Panel::test(FieldsScreen::class);

    expect(array_column($screen->options('owner', 'iva'), 'label'))->toBe(['Ivan Petrov', 'Oleg Ivanov'])
        ->and($screen->options('owner', 'rocont'))->toBe([['value' => $this->anna->id, 'label' => 'Anna Smirnova']])
        ->and($screen->options('owner', (string) $this->oleg->id)[0]['label'])->toBe('Oleg Ivanov')
        ->and($screen->options('owner', '100%'))->toBe([])
        ->and($screen->options('watchers', 'anna')[0]['label'])->toBe('Anna Smirnova <anna@rocont.dev>');
});

it('narrows a relation by other fields of the form', function () {
    $screen = Panel::test(FieldsScreen::class);
    expect(array_column($screen->options('colleague'), 'label'))->toBe(['Ivan Petrov', 'Oleg Ivanov']);

    $screen->set('domain', 'rocont.dev');
    expect(array_column($screen->options('colleague'), 'label'))->toBe(['Anna Smirnova']);
});

it('accepts only records of the relation query and shows the chosen ones', function () {
    Panel::test(FieldsScreen::class)
        ->set('colleague', $this->anna->id)->call('save')->assertHasErrors('colleague')
        ->set('colleague', $this->ivan->id)->set('owner', 999)->call('save')->assertHasErrors('owner')
        ->set('owner', $this->oleg->id)->set('watchers', [$this->anna->id, $this->ivan->id, ['x']])->call('save')->assertHasNoErrors()
        ->assertSet('watchers', [$this->anna->id, $this->ivan->id])
        ->assertNode('watchers', fn (array $node) => expect($node['selected'])->toBe([
            ['value' => $this->anna->id, 'label' => 'Anna Smirnova <anna@rocont.dev>'],
            ['value' => $this->ivan->id, 'label' => 'Ivan Petrov <ivan@example.com>'],
        ]));
});

it('refuses a relation search on a field that is not a relation', function () {
    Panel::test(FieldsScreen::class)->options('size', 'x');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('accepts only the options of a select unless custom values are allowed', function () {
    Panel::test(FieldsScreen::class)
        ->set('size', 'xl')->call('save')->assertHasErrors('size')
        ->set('size', 'm')->set('tags', ['new', 'decaf'])->call('save')->assertHasNoErrors()
        ->assertSet('tags', ['new', 'decaf'])
        ->assertNode('tags', fn (array $node) => expect($node)->toMatchArray(['allowCustom' => true, 'searchable' => true]));
});

it('describes the fields that came from Orchid in the IR', function () {
    $screen = Panel::test(FieldsScreen::class);

    expect($screen->node('owner'))->toMatchArray(['type' => 'relation', 'label' => 'Owner'])
        ->and($screen->node('prices'))->toMatchArray(['type' => 'repeater', 'table' => true, 'addLabel' => 'Add a price'])
        ->and(array_column($screen->node('prices')['fields'], 'label'))->toBe(['Volume', 'Price'])
        ->and($screen->node('location'))->toMatchArray(['type' => 'map', 'zoom' => 15, 'center' => ['lat' => 59.93, 'lng' => 30.31], 'tiles' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'])
        ->and($screen->node('link'))->toMatchArray(['type' => 'utm'])
        ->and($screen->node('number'))->toMatchArray(['type' => 'label', 'label' => 'Number'])
        ->and($screen->node('timezone'))->toMatchArray(['type' => 'select', 'searchable' => true])
        ->and($screen->node('timezone')['options'])->toContain(['value' => 'Europe/Moscow', 'label' => 'Europe/Moscow', 'hint' => 'UTC+03:00'])
        ->and($screen->node('phone'))->toMatchArray(['type' => 'input', 'mask' => '+7 (999) 999-99-99', 'suggestions' => ['+7 (800) 555-35-35']])
        ->and($screen->node('opensAt'))->toMatchArray(['type' => 'date', 'timeOnly' => true]);
});

it('keeps a map point only with valid coordinates', function () {
    Panel::test(FieldsScreen::class)
        ->set('location', ['lat' => '59.9386', 'lng' => 30.3141, 'zoom' => 3])->assertSet('location', ['lat' => 59.9386, 'lng' => 30.3141])
        ->set('location', ['lat' => 200, 'lng' => 10])->assertSet('location', null);
});

it('never takes a label from the browser', function () {
    Panel::test(FieldsScreen::class)->set('number', 'hacked')->call('save')->assertToast('Saved A-1042');
});

it('renders a Blade view among the nodes, escaped by Blade', function () {
    expect(Panel::test(FieldsScreen::class)->node('island'))->toMatchArray([
        'type' => 'view',
        'html' => "<h3>Order A-1042</h3>\n<p>Placed by &lt;Ivan &amp; Co&gt; · <a href=\"/admin/users\">all customers</a></p>\n",
    ]);
});

it('explains a missing Blade view', function () {
    Vanda\Layouts\Layout::view('admin.missing')->toSchema(new Vanda\Schema\SchemaContext(new FieldsScreen, []));
})->throws(Vanda\VandaException::class, 'Create resources/views/admin/missing.blade.php');
