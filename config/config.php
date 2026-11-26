<?php

declare(strict_types=1);

define('NOME_APLICACAO', 'DECOLAE');

define('TAGLINE_APLICACAO', 'Seu próximo destino começa aqui.');

define('HOSPEDEIRO_BANCO_DADOS', getenv('DECOLAE_DB_HOST') ?: '127.0.0.1');

define('PORTA_BANCO_DADOS', getenv('DECOLAE_DB_PORT') ?: '3306');

define('NOME_BANCO_DADOS', getenv('DECOLAE_DB_NAME') ?: 'decolae');

define('USUARIO_BANCO_DADOS', getenv('DECOLAE_DB_USER') ?: 'root');

define('SENHA_BANCO_DADOS', getenv('DECOLAE_DB_PASS') ?: '');

date_default_timezone_set('America/Sao_Paulo');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!defined('DB_HOST')) define('DB_HOST', HOSPEDEIRO_BANCO_DADOS);
if (!defined('DB_PORT')) define('DB_PORT', PORTA_BANCO_DADOS);
if (!defined('DB_NAME')) define('DB_NAME', NOME_BANCO_DADOS);
if (!defined('DB_USER')) define('DB_USER', USUARIO_BANCO_DADOS);
if (!defined('DB_PASS')) define('DB_PASS', SENHA_BANCO_DADOS);
if (!defined('APP_NAME')) define('APP_NAME', NOME_APLICACAO);
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', TAGLINE_APLICACAO);
