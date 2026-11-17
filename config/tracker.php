<?php

return [

    // The git commit this build was made from. Render sets RENDER_GIT_COMMIT
    // on every deploy; GET /version reports it.
    'commit' => env('RENDER_GIT_COMMIT'),

    // Passwords for the two seeded demo accounts. Supplied by the environment
    // so that no credential is ever committed. See database/seeders/DemoSeeder.php.
    'demo' => [
        'admin_password' => env('DEMO_ADMIN_PASSWORD'),
        'member_password' => env('DEMO_MEMBER_PASSWORD'),
    ],

];
