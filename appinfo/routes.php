<?php

return [
    'routes' => [
        ['name' => 'settings#saveSettings',       'url' => '/settings/save', 'verb' => 'POST'],
        ['name' => 'authentication#casLogin',     'url' => '/login',         'verb' => 'GET'],
        ['name' => 'authentication#casLogout',    'url' => '/login',         'verb' => 'POST'],
    ],
];
