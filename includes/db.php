<?php

/**
 * Aqui a gente concentra a conexão da aplicação com o banco.
 * 
 * A conexão fica guardada e é reutilizada durante a mesma requisição.
 */

declare(strict_types=1);

// Aqui a gente carrega as configurações de acesso ao banco.
require_once __DIR__ . '/../config/config.php';

/**
 * Aqui a gente cria a conexão com o banco ou reutiliza a que já está aberta.
 * 
 * O PDO fica configurado para lançar erros e devolver resultados associativos.
 * 
 * @return PDO Instância de conexão com o banco de dados
 */
function obter_conexao_banco_dados(): PDO
{
    // Essa variável mantém a mesma conexão entre as chamadas da função.
    static $conexao_pdo = null;

    // Se a conexão já foi criada, a gente devolve ela.
    if ($conexao_pdo instanceof PDO) {
        return $conexao_pdo;
    }

    // Aqui a gente monta o endereço de conexão usado pelo PDO.
    $string_conexao = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        HOSPEDEIRO_BANCO_DADOS,
        PORTA_BANCO_DADOS,
        NOME_BANCO_DADOS
    );

    // Agora a gente abre a conexão com as opções definidas.
    $conexao_pdo = new PDO($string_conexao, USUARIO_BANCO_DADOS, SENHA_BANCO_DADOS, [
        // Se a conexão falhar, a gente deixa o erro aparecer.
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Assim os resultados podem ser acessados pelo nome das colunas.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Aqui a gente usa prepared statements nativos do banco.
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $conexao_pdo;
}

/**
 * Esse atalho mantém compatibilidade com páginas que usam o nome antigo.
 * 
 * @return PDO Instância de conexão com o banco de dados
 */
function db(): PDO
{
    return obter_conexao_banco_dados();
}
