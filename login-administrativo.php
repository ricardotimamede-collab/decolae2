<?php

/**
 * Aqui a gente concentra a entrada dos perfis que cuidam da operação.
 * 
 * Administradores, gerentes e tripulantes entram por esta tela.
 */

declare(strict_types=1);

// Aqui a gente carrega as funções e o layout usados no login.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Cada perfil leva a pessoa para sua própria área depois do login.
$perfis_administrativos = [
    'admin' => [
        'label' => 'Administrador',
        'rota' => 'admin.php'
    ],
    'gerente' => [
        'label' => 'Gerente',
        'rota' => 'gerente.php'
    ],
    'tripulante' => [
        'label' => 'Tripulante',
        'rota' => 'tripulante.php'
    ],
];

// Essas variáveis guardam os avisos do formulário.
$perfil_selecionado = strtolower((string) ($_GET['perfil'] ?? $_POST['perfil'] ?? 'admin'));

if (!isset($perfis_administrativos[$perfil_selecionado])) {
    $perfil_selecionado = 'admin';
}

$mensagem_erro = null;

// Aqui a gente valida os dados enviados para entrar.
if (eh_post()) {
    try {
        $conexao_pdo = db();
        
        verificar_token_csrf($_POST['token_csrf'] ?? null);
        
        $email_inserido = trim((string) ($_POST['email'] ?? ''));
        $senha_inserida = (string) ($_POST['senha'] ?? '');
        
        if (!filter_var($email_inserido, FILTER_VALIDATE_EMAIL) || $senha_inserida === '') {
            throw new InvalidArgumentException('Informe email e senha válidos.');
        }
        
        $comando = $conexao_pdo->prepare('SELECT cpf, senha FROM usuario WHERE email = ?');
        $comando->execute([$email_inserido]);
        $dados_conta = $comando->fetch();
        
        if (
            !$dados_conta || 
            !password_verify($senha_inserida, $dados_conta['senha']) || 
            !verificar_perfil_login_existe($conexao_pdo, $dados_conta['cpf'], $perfil_selecionado)
        ) {
            throw new InvalidArgumentException('Email, senha ou perfil não correspondem.');
        }
        
        session_regenerate_id(true);
        
        $_SESSION['cpf_usuario'] = $dados_conta['cpf'];
        $_SESSION['papel_usuario'] = $perfil_selecionado;
        
        log_action(
            $conexao_pdo,
            'LOGIN_' . strtoupper($perfil_selecionado),
            'Login realizado no perfil ' . $perfis_administrativos[$perfil_selecionado]['label'] . '.',
            'usuario:' . $dados_conta['cpf'],
            $dados_conta['cpf']
        );
        
        $rota_destino = $perfis_administrativos[$perfil_selecionado]['rota'];
        
        header('Location: ' . $rota_destino);
        exit;
    } catch (Throwable $excecao) {
        $mensagem_erro = $excecao->getMessage();
    }
}

renderizar_cabecalho('Login Administrativo - ' . $perfis_administrativos[$perfil_selecionado]['label'], null);
?>

<section class="tela-login">
    <div class="login-admin-container">
        
        <div class="login-admin-colunas">
            
            <!-- Aqui a pessoa escolhe qual perfil quer acessar. -->
            <div>
                <h1 class="login-admin-titulo">Área Administrativa</h1>
                <p class="login-admin-descricao">Selecione seu perfil de acesso</p>
                
                <div class="lista-perfis">
                    <?php foreach ($perfis_administrativos as $chave_perfil => $dados_perfil): ?>
                        <a 
                            href="login-administrativo.php?perfil=<?= esc($chave_perfil) ?>" 
                            class="opcao-perfil <?= $chave_perfil === $perfil_selecionado ? 'opcao-perfil-ativa' : '' ?>"
                        >
                            <strong class="opcao-perfil-titulo">
                                <?= esc($dados_perfil['label']) ?>
                            </strong>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div class="aviso-login-cliente">
                    <strong class="aviso-login-cliente-titulo">Você é um cliente?</strong>
                    <a class="link-login-cliente" href="login-cliente.php">
                        Ir para login de cliente →
                    </a>
                </div>
            </div>
            
            <!-- Depois de escolher o perfil, a pessoa informa os dados da conta. -->
            <div class="cartao-login">
                
                <?php if ($mensagem_erro): ?>
                    <div class="aviso aviso-login-erro">
                        <?= esc($mensagem_erro) ?>
                    </div>
                <?php endif; ?>
                
                <h2 class="titulo-login">
                    Login - <?= esc($perfis_administrativos[$perfil_selecionado]['label']) ?>
                </h2>
                <p class="descricao-login">Entre com suas credenciais</p>
                
                <form method="post">
                    <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
                    <input type="hidden" name="perfil" value="<?= esc($perfil_selecionado) ?>">
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Email</label>
                        <input 
                            name="email" 
                            type="email" 
                            placeholder="seu@email.com" 
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form campo-login-form-final">
                        <label class="rotulo-login">Senha</label>
                        <input 
                            name="senha" 
                            type="password" 
                            placeholder="••••••••" 
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <button class="botao-login" type="submit">
                        Entrar
                    </button>
                </form>
            </div>
        
        </div>
    </div>
</section>

<?php renderizar_rodape(); ?>
