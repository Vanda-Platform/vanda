<?php

return [

    /*
     * Brand colour of the panel. Every other accent shade is derived from it
     * in the browser, so changing it never requires an asset rebuild.
     */
    'brand' => env('VANDA_BRAND', '#12805C'),

    /*
     * Record the PHP file and line where every schema node was created and
     * expose it in the IR as `source`. Enabled automatically in local.
     */
    'dev' => env('VANDA_DEV'),

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
     * Upload fields store files on this disk, in Orchid's `attachments` table.
     */
    'attachments' => [
        'disk' => env('VANDA_ATTACHMENTS_DISK', 'public'),
    ],
];
