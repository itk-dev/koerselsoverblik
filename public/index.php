<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    /** @var array{APP_ENV: string, APP_DEBUG: bool|int|string} $context The context from the Symfony runtime. */
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
