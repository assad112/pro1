<?php
declare(strict_types=1);

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/i18n.php';
require_once __DIR__ . '/../app/roles.php';
require_once __DIR__ . '/../app/repositories/pmms.php';
set_language_from_request();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
