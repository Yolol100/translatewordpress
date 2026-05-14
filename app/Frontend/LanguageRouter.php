<?php

declare(strict_types=1);

namespace Webactueel\Translate\Frontend;

use Webactueel\Translate\Frontend\Routing\PathHelpers;
use Webactueel\Translate\Frontend\Routing\LanguageRedirects;
use Webactueel\Translate\Frontend\Routing\RequestState;
use Webactueel\Translate\Frontend\Routing\RewriteRouting;

if (! defined('ABSPATH')) {
    exit;
}

final class LanguageRouter
{
    use RewriteRouting;
    use PathHelpers;
    use RequestState;
    use LanguageRedirects;

    private static string $requestLanguage = '';
    private static string $requestPath = '';
}
