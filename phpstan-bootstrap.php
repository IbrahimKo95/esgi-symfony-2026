<?php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__.'/vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(__DIR__.'/.env')) {
    (new Dotenv())->bootEnv(__DIR__.'/.env');
}

$env = $_SERVER['APP_ENV'] ?? 'dev';
$kernel = new Kernel($env, $env !== 'prod');
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
