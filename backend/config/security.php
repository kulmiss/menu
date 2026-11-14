<?php

return [
    // Strict-Transport-Security on HTTPS answers (SECURITY_REPORT.md H1). Browsers then
    // refuse plain http for a year. Turn subdomains on only if every subdomain has HTTPS.
    'hsts' => (bool) env('SECURITY_HSTS', true),
    'hsts_subdomains' => (bool) env('SECURITY_HSTS_SUBDOMAINS', false),
];
