<?php

/**
 * Aqui a gente reúne as configurações usadas pelo sistema.
 * 
 * Também deixamos aqui o acesso ao banco, o fuso horário e a sessão.
 */

declare(strict_types=1);

// Aqui a gente define os dados gerais do sistema.

/** Nome que aparece no sistema. */
define('NOME_APLICACAO', 'DECOLAE');

/** Frase curta que acompanha o nome do sistema. */
define('TAGLINE_APLICACAO', 'Seu próximo destino começa aqui.');

// Aqui a gente lê os dados de conexão com o banco.

/** Servidor MySQL, configurável pelo ambiente. */
define('HOSPEDEIRO_BANCO_DADOS', getenv('DECOLAE_DB_HOST') ?: '127.0.0.1');

/** Porta do MySQL, configurável pelo ambiente. */
define('PORTA_BANCO_DADOS', getenv('DECOLAE_DB_PORT') ?: '3306');

/** Banco que a aplicação vai acessar. */
define('NOME_BANCO_DADOS', getenv('DECOLAE_DB_NAME') ?: 'decolae');

/** Usuário usado na conexão com o banco. */
define('USUARIO_BANCO_DADOS', getenv('DECOLAE_DB_USER') ?: 'root');

/** Senha usada na conexão com o banco. */
define('SENHA_BANCO_DADOS', getenv('DECOLAE_DB_PASS') ?: '');

// Aqui a gente mantém datas e horários no fuso local.

/**
 * Aqui a gente usa o horário de São Paulo.
 * 
 * Assim, os horários exibidos ficam alinhados com os do sistema.
 */
date_default_timezone_set('America/Sao_Paulo');

// Aqui a gente inicia a sessão só quando ainda não existe uma ativa.

/**
 * A sessão guarda os dados de quem está conectado.
 * 
 * Antes de iniciar, a gente confere se o PHP já não abriu uma.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Aqui a gente mantém nomes antigos para não quebrar páginas que ainda os usam.

/**
 * Esses nomes alternativos ajudam a manter compatibilidade com partes antigas do sistema.
 */
if (!defined('DB_HOST')) define('DB_HOST', HOSPEDEIRO_BANCO_DADOS);
if (!defined('DB_PORT')) define('DB_PORT', PORTA_BANCO_DADOS);
if (!defined('DB_NAME')) define('DB_NAME', NOME_BANCO_DADOS);
if (!defined('DB_USER')) define('DB_USER', USUARIO_BANCO_DADOS);
if (!defined('DB_PASS')) define('DB_PASS', SENHA_BANCO_DADOS);
if (!defined('APP_NAME')) define('APP_NAME', NOME_APLICACAO);
if (!defined('APP_TAGLINE')) define('APP_TAGLINE', TAGLINE_APLICACAO);
