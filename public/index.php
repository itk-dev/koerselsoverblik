<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    /** @var string $environment The Symfony runtime always sets APP_ENV. */
    $environment = $context['APP_ENV'];

    return new Kernel($environment, (bool) $context['APP_DEBUG']);
};
