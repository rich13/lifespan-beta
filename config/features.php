<?php

return [
    /*
    | Admin "switch user" impersonation. Off by default so the switcher is not
    | rendered, requested, or run on every page. Set USER_SWITCHER_ENABLED=true
    | to turn it back on.
    */
    'user_switcher_enabled' => (bool) env('USER_SWITCHER_ENABLED', false),
];
