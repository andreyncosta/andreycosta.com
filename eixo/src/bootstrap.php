<?php
declare(strict_types=1);

// The Composer dependency (smalot/pdfparser, the pure-PHP text layer) is installed on the
// server with `composer install --no-dev` inside eixo/; tests run without it.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
require __DIR__ . '/core.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/ucum.php';
require __DIR__ . '/extraction.php';
require __DIR__ . '/data.php';
require __DIR__ . '/exames.php';
require __DIR__ . '/views.php';
require __DIR__ . '/synthetic.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
