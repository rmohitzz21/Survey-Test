<?php
// sp_is_https() decides the Secure flag on the session cookie. Get it wrong in the
// "yes" direction on a plain-HTTP host and every browser silently drops the cookie,
// which is a login loop nobody can work around. Worth a check of its own.
//
// Run: php tests/session-cookie-test.php

// auth.php is pulled in for sp_is_https() only; json_response()/session calls are
// never reached, so no session or DB is needed here.
require_once __DIR__ . '/../includes/auth.php';

$cases = [
    // [expected, $_SERVER, description]
    [false, [],                                          'bare HTTP, nothing set'],
    [false, ['HTTPS' => 'off'],                           "Apache/IIS write HTTPS='off' on plain HTTP"],
    [false, ['HTTPS' => 'OFF'],                           'the same, upper case'],
    [false, ['HTTPS' => ''],                              'empty string is not TLS'],
    [true,  ['HTTPS' => 'on'],                            'direct TLS'],
    [true,  ['HTTPS' => '1'],                             'some SAPIs report 1'],
    [true,  ['HTTP_X_FORWARDED_PROTO' => 'https'],        'behind a proxy / CDN'],
    [true,  ['HTTP_X_FORWARDED_PROTO' => 'HTTPS'],        'forwarded header, upper case'],
    [false, ['HTTP_X_FORWARDED_PROTO' => 'http'],         'proxy says plain'],
    [true,  ['HTTP_X_FORWARDED_SSL' => 'on'],             'older proxies send this instead'],
    [true,  ['SERVER_PORT' => '443'],                     'port 443 with no other signal'],
    [false, ['SERVER_PORT' => '80'],                      'port 80'],
    [true,  ['HTTPS' => 'off', 'SERVER_PORT' => '443'],   "'off' must not veto a real 443"],
];

$failed = 0;
foreach ($cases as [$expected, $server, $why]) {
    $_SERVER = $server;
    $got = sp_is_https();
    if ($got !== $expected) {
        $failed++;
        printf("FAIL: %s — expected %s, got %s\n", $why,
               var_export($expected, true), var_export($got, true));
    }
}

if ($failed) {
    echo "session-cookie-test: $failed of " . count($cases) . " failed\n";
    exit(1);
}
echo 'session-cookie-test: all ' . count($cases) . " assertions passed\n";
