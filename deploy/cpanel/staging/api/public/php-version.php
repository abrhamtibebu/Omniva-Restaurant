<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP version: '.PHP_VERSION."\n";
echo 'SAPI: '.PHP_SAPI."\n";
echo 'Required: >= 8.2.0'."\n";
echo version_compare(PHP_VERSION, '8.2.0', '>=')
    ? "OK — this domain can run the API.\n"
    : "FAIL — in cPanel MultiPHP Manager set this domain to PHP 8.2 or 8.3, then reload.\n";

echo "\nDelete this file after checking.\n";
