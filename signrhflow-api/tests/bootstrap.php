<?php

declare(strict_types=1);

/**
 * Evita commitar APP_KEY no repositório (alertas tipo GitGuardian).
 * Se APP_KEY não estiver definida no ambiente, gera uma chave aleatória só para a suíte PHPUnit.
 */
$key = getenv('APP_KEY');
if ($key === false || $key === '') {
    $generated = 'base64:'.base64_encode(random_bytes(32));
    putenv('APP_KEY='.$generated);
    $_ENV['APP_KEY'] = $generated;
    $_SERVER['APP_KEY'] = $generated;
}

require dirname(__DIR__).'/vendor/autoload.php';
