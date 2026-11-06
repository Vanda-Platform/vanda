<?php

return [
    /*
     * Old route names keep working: with 'admin.' => 'platform.', route('platform.ticket.edit')
     * gives the URL of the route named 'admin.ticket.edit'. A name that already exists is left alone.
     */
    'route_aliases' => [
        'admin.' => 'platform.',
    ],
];
