<?php

declare(strict_types=1);

namespace Webactueel\Translate\Automation;

if (! defined('ABSPATH')) {
    exit;
}

final class TranslationJobLimits
{
    public const MAX_BATCH_SIZE = 20;
    public const MAX_AI_TEXT_LENGTH = 5000;
}
