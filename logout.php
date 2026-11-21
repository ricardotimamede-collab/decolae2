<?php

/**
 * Aqui a gente encerra a sessão de quem saiu da conta.
 * 
 * Também removemos o cookie do navegador e voltamos para a tela de login.
 */

declare(strict_types=1);

// A configuração inicia a sessão antes de a gente limpá-la.
require_once __DIR__ . '/config/config.php';

/**
 * Aqui a gente remove os dados guardados para a conta conectada.
 */
$_SESSION = [];

/**
 * Se a sessão usa cookie, a gente também precisa removê-lo do navegador.
 */
if (ini_get('session.use_cookies')) {
    // Primeiro a gente lê as opções do cookie atual.
    $parametros_cookie = session_get_cookie_params();
    
    // Uma data passada faz o navegador apagar o cookie.
    setcookie(
        session_name(),              // A gente mantém o nome usado pela sessão.
        '',                          // O valor vazio acompanha a remoção.
        time() - 42000,              // A data passada pede que o navegador apague o cookie.
        $parametros_cookie['path'],  // Mantemos o caminho original do cookie.
        $parametros_cookie['domain'],// E também o domínio original.
        $parametros_cookie['secure'],// A opção HTTPS continua igual à original.
        $parametros_cookie['httponly']// O cookie continua sem acesso pelo JavaScript.
    );
}

/**
 * Depois de limpar os dados, a gente encerra a sessão no servidor.
 */
session_destroy();

/**
 * Por fim, a gente volta para o login com um aviso de saída concluída.
 */
header('Location: login-cliente.php?logout=1');

// O redirecionamento já foi enviado, então encerramos aqui.
exit;
