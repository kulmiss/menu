<?php

/*
 * No cross-origin access (SECURITY_REPORT.md L1). The admin is served from the
 * same site and the mobile app is not a browser, so neither needs CORS; without
 * this file Laravel's default allowed any origin on api/*.
 */
return [
    'paths' => [],
    'allowed_methods' => [],
    'allowed_origins' => [],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
