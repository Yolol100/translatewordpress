<?php
/**
 * Test HTTP Accept-Language qvalue parsing without a WordPress installation.
 * No browser redirect, cookie, network access or website state is changed.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/app/Frontend/Concerns/CookieHelpers.php';
require dirname(__DIR__) . '/app/Frontend/Routing/BrowserLanguageRedirector.php';

$reflection = new ReflectionClass(\Webactueel\Translate\Frontend\Routing\BrowserLanguageRedirector::class);
$quality = $reflection->getMethod('browser_language_quality');
$quality->setAccessible(true);
$cases = [
    'default quality' => [[], 1.0],
    'normal qvalue' => [['q=0.9'], 0.9],
    'zero quality' => [['q=0'], 0.0],
    'one quality' => [['q=1'], 1.0],
    'valid three decimals' => [['q=0.123'], 0.123],
    'valid one with decimal zeros' => [['q=1.000'], 1.0],
    'uppercase Q' => [['Q=0.4'], 0.4],
    'above one' => [['q=1.2'], 0.0],
    'negative quality' => [['q=-0.1'], 0.0],
    'trailing characters' => [['q=0.9garbage'], 0.0],
    'exponent notation' => [['q=1e309'], 0.0],
    'too many decimal places' => [['q=0.1234'], 0.0],
    'invalid one decimals' => [['q=1.001'], 0.0],
    'multiple qvalues' => [['q=0.2', 'q=0.8'], 0.0],
    'unsupported parameter' => [['foo=bar'], 0.0],
];

foreach ($cases as $name => $case) {
    [$parameters, $expected] = $case;
    $pieces = array_merge(['en'], $parameters);
    $actual = $quality->invoke(null, $pieces);
    if (!is_float($actual) || !is_finite($actual) || abs($actual - $expected) > 0.000001) {
        fwrite(STDERR, sprintf(
            "Invalid Accept-Language quality (%s): expected %.3f, got %s\n",
            $name,
            $expected,
            var_export($actual, true)
        ));
        exit(1);
    }
}

echo "RFC 9110 Accept-Language quality contract: OK\n";
