<?php

use App\Kernel;

require_once __DIR__.'/vendor/autoload.php';

$env = $_SERVER['APP_ENV'] ?? 'dev';
$kernel = new Kernel($env, $env !== 'prod');
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
