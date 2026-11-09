<?php

return [

    /*
     * Brand colour of the panel. Every other accent shade is derived from it
     * in the browser, so changing it never requires an asset rebuild.
     */
    'brand' => env('VANDA_BRAND', '#12805C'),

    /*
     * Development mode: record the PHP file and line where every schema node was created
     * (`source` in the IR) and check every page for paths, modals and icons that do not exist.
     * On by itself locally and in tests (APP_ENV=testing); true or false decides instead.
     */
    'dev' => env('VANDA_DEV'),

    /*
     * How much fits on a screen: 'comfortable' or 'compact' (32px fields, tighter table rows,
     * cards and menu). Each user may switch to the other one under the menu; phones always
     * get the comfortable sizes.
     */
    'density' => env('VANDA_DENSITY', 'comfortable'),

    /*
     * Name shown in the sidebar and the browser tab.
     */
    'name' => env('VANDA_NAME', 'Vanda'),

    /*
     * Auth guard of the panel and where to go after signing in:
     * a URL or a screen class. Null = the first menu item.
     */
    'guard' => env('VANDA_GUARD', 'web'),

    'home' => null,

    /*
     * Middleware of every route registered with Route::vanda().
     */
    'middleware' => ['web'],

    /*
     * Run the bundled migrations for roles, permissions and attachments (skipped
     * automatically where Orchid's tables already exist). Disable to publish and edit it instead.
     */
    'migrations' => true,

    /*
     * The activity log (Vanda\Activity): changes of models with RecordsActivity and the buttons
     * pressed in the panel. Entries older than keep_days are removed by
     * `php artisan model:prune --model="Vanda\Activity\Activity"` (null keeps everything).
     */
    'activity' => [
        'enabled' => env('VANDA_ACTIVITY', true),
        'keep_days' => 365,
    ],

    /*
     * Upload fields store files on this disk, in Orchid's `attachments` table.
     */
    'attachments' => [
        'disk' => env('VANDA_ATTACHMENTS_DISK', 'public'),
    ],

    /*
     * The picture behind the sign-in card: null for Vanda's liquid glass, a URL of your own
     * (e.g. '/images/auth.webp' from your public folder: wide, 2560 px or more, calm in the middle,
     * where the card sits), or false for the plain background.
     */
    'auth_background' => env('VANDA_AUTH_BACKGROUND'),

    /*
     * The tab icon: a URL of your own, or null for Vanda's orchid.
     */
    'favicon' => env('VANDA_FAVICON'),

    /*
     * The red strip about a weak password: null shows it on production only (APP_ENV=production),
     * true or false everywhere or nowhere.
     */
    'weak_password_banner' => env('VANDA_WEAK_PASSWORD_BANNER'),

    /*
     * Map fields: the tile server, the address search (a Nominatim-compatible URL, or null to
     * turn search off) and where an empty map opens. The browser calls these servers itself.
     */
    'map' => [
        'tiles' => env('VANDA_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; OpenStreetMap',
        'geocoder' => env('VANDA_MAP_GEOCODER', 'https://nominatim.openstreetmap.org/search'),
        'center' => [55.7558, 37.6173],
    ],
];
