<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function obter_conexao_banco_dados(): PDO
{
    static $conexao_pdo = null;

    if ($conexao_pdo instanceof PDO) {
        return $conexao_pdo;
    }

    $string_conexao = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        HOSPEDEIRO_BANCO_DADOS,
        PORTA_BANCO_DADOS,
        NOME_BANCO_DADOS
    );

    $conexao_pdo = new PDO($string_conexao, USUARIO_BANCO_DADOS, SENHA_BANCO_DADOS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $conexao_pdo;
}

function db(): PDO
{
    return obter_conexao_banco_dados();
}
