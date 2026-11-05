<?php

return [

    /*
     * Brand colour of the panel. Every other accent shade is derived from it
     * in the browser, so changing it never requires an asset rebuild.
     */
    'brand' => env('VANDA_BRAND', '#12805C'),

    /*
     * Record the PHP file and line where every schema node was created and
     * expose it in the IR as `dev.source`. Enabled automatically in local.
     */
    'dev' => env('VANDA_DEV'),

    /*
     * Name shown in the sidebar and the browser tab.
     */
    'name' => env('VANDA_NAME', 'Vanda'),
];
