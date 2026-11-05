<?php

return [
    'home' => 'Go to the start page',
    'back' => 'Back',
    'code' => 'Error :code',
    401 => ['title' => 'Sign in required', 'text' => 'Sign in to the panel to open this page.'],
    403 => ['title' => 'No access', 'text' => 'Your account has no permission for this page. Ask an administrator if you need it.'],
    404 => ['title' => 'Page not found', 'text' => 'It may have been removed, or the address has a typo.'],
    419 => ['title' => 'Page expired', 'text' => 'Your session has ended. Reload the page and try again.'],
    429 => ['title' => 'Too many requests', 'text' => 'Wait a moment and try again.'],
    500 => ['title' => 'Something broke', 'text' => 'Try again a little later.'],
    503 => ['title' => 'Maintenance in progress', 'text' => 'The panel will be back shortly.'],
];
