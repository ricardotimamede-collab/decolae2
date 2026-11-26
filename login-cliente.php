<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$mensagem_erro = null;
$mensagem_sucesso = null;
$modo_atual = 'login';

if (!eh_post() && in_array($_GET['modo'] ?? '', ['cadastro', 'ativar'], true)) {
    $modo_atual = $_GET['modo'];
}

if (eh_post()) {
    $conexao_pdo = db();
    
    $modo_atual = $_POST['modo'] ?? 'login';
    
    try {
        verificar_token_csrf($_POST['token_csrf'] ?? null);
        
        if ($modo_atual === 'login') {
            $email_inserido = trim((string) ($_POST['email'] ?? ''));
            $senha_inserida = (string) ($_POST['senha'] ?? '');
            
            if (!filter_var($email_inserido, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Email inválido.');
            }
            if ($senha_inserida === '') {
                throw new InvalidArgumentException('Senha é obrigatória.');
            }
            
            $comando = $conexao_pdo->prepare('SELECT cpf, senha FROM usuario WHERE email = ?');
            $comando->execute([$email_inserido]);
            $dados_conta = $comando->fetch();
            
            if (
                !$dados_conta || 
                !password_verify($senha_inserida, $dados_conta['senha']) || 
                !verificar_perfil_login_existe($conexao_pdo, $dados_conta['cpf'], 'cliente')
            ) {
                throw new InvalidArgumentException('Email ou senha incorretos.');
            }
            
            session_regenerate_id(true);
            
            $_SESSION['cpf_usuario'] = $dados_conta['cpf'];
            $_SESSION['papel_usuario'] = 'cliente';
            
            log_action(
                $conexao_pdo,
                'LOGIN_CLIENTE',
                'Cliente realizou login.',
                'usuario:' . $dados_conta['cpf'],
                $dados_conta['cpf']
            );
            
            header('Location: index.php');
            exit;
        }
        
        elseif ($modo_atual === 'cadastro') {
            $nome_completo = trim((string) ($_POST['nome_completo'] ?? ''));
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $email = trim((string) ($_POST['email_cadastro'] ?? ''));
            $telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
            $endereco = trim((string) ($_POST['endereco'] ?? ''));
            $senha = (string) ($_POST['senha_cadastro'] ?? '');
            $confirmacao_senha = (string) ($_POST['confirmacao_senha'] ?? '');
            
            if ($nome_completo === '') {
                throw new InvalidArgumentException('Nome é obrigatório.');
            }
            
            if ($cpf === '') {
                throw new InvalidArgumentException('CPF é obrigatório.');
            }
            
            if (strlen($cpf) !== 11) {
                throw new InvalidArgumentException('O CPF deve ter 11 dígitos.');
            }
            
            if ($email === '') {
                throw new InvalidArgumentException('Email é obrigatório.');
            }
            
            if ($telefone === '') {
                throw new InvalidArgumentException('Telefone é obrigatório.');
            }
            
            if ($senha === '') {
                throw new InvalidArgumentException('Senha é obrigatória.');
            }
            
            if ($senha !== $confirmacao_senha) {
                throw new InvalidArgumentException('As senhas não coincidem.');
            }
            
            $comando = $conexao_pdo->prepare('SELECT 1 FROM usuario WHERE cpf = ?');
            $comando->execute([$cpf]);
            $conta_existente = $comando->fetch();
            
            $comando = $conexao_pdo->prepare('SELECT 1 FROM usuario WHERE telefone = ? AND cpf <> ?');
            $comando->execute([$telefone, $cpf]);
            if ($comando->fetchColumn()) {
                throw new InvalidArgumentException('Este telefone já está cadastrado.');
            }
            
            if ($conta_existente) {
                throw new InvalidArgumentException(
                    verificar_perfil_login_existe($conexao_pdo, $cpf, 'cliente')
                        ? 'Este CPF já está cadastrado.'
                        : 'Este CPF já tem conta de funcionário. Use a opção Já tenho conta de funcionário.'
                );
            } else {
                $comando = $conexao_pdo->prepare('SELECT 1 FROM usuario WHERE email = ?');
                $comando->execute([$email]);
                if ($comando->fetchColumn()) {
                    throw new InvalidArgumentException('Este email já está em uso.');
                }
                
                if ($endereco === '') {
                    throw new InvalidArgumentException('Endereço é obrigatório.');
                }
                
                $conexao_pdo->beginTransaction();
                
                try {
                    $comando = $conexao_pdo->prepare(
                        'INSERT INTO usuario (cpf, nome_completo, email, endereco, telefone, senha) 
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $comando->execute([
                        $cpf,
                        $nome_completo,
                        $email,
                        $endereco,
                        $telefone,
                        password_hash($senha, PASSWORD_BCRYPT)
                    ]);
                    
                    $comando_cliente = $conexao_pdo->prepare('INSERT INTO cliente (cpf) VALUES (?)');
                    $comando_cliente->execute([$cpf]);
                    
                    $conexao_pdo->commit();
                    
                    log_action(
                        $conexao_pdo,
                        'CLIENTE_REGISTRADO',
                        'Novo cliente se registrou na plataforma.',
                        'usuario:' . $cpf,
                        $cpf
                    );
                    
                    $mensagem_sucesso = 'Cadastro realizado com sucesso! Agora você pode fazer login.';
                    $modo_atual = 'login';
                } catch (Throwable $excecao) {
                    $conexao_pdo->rollBack();
                    throw $excecao;
                }
            }
        }
        elseif ($modo_atual === 'ativar') {
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $email = trim((string) ($_POST['email_ativacao'] ?? ''));
            $senha = (string) ($_POST['senha_ativacao'] ?? '');

            if (strlen($cpf) !== 11) {
                throw new InvalidArgumentException('O CPF deve ter 11 dígitos.');
            }

            if ($email === '') {
                throw new InvalidArgumentException('Email é obrigatório.');
            }

            if ($senha === '') {
                throw new InvalidArgumentException('Senha é obrigatória.');
            }

            $comando = $conexao_pdo->prepare('SELECT email, senha FROM usuario WHERE cpf = ?');
            $comando->execute([$cpf]);
            $conta = $comando->fetch();

            if (!$conta) {
                throw new InvalidArgumentException('Não existe conta com esse CPF. Use a opção Nova conta.');
            }

            if (verificar_perfil_login_existe($conexao_pdo, $cpf, 'cliente')) {
                throw new InvalidArgumentException('Este CPF já tem conta de cliente. Faça login.');
            }

            if (strcasecmp($conta['email'], $email) !== 0 || !password_verify($senha, $conta['senha'])) {
                throw new InvalidArgumentException('Email ou senha incorretos.');
            }

            $comando = $conexao_pdo->prepare('INSERT INTO cliente (cpf) VALUES (?)');
            $comando->execute([$cpf]);

            log_action(
                $conexao_pdo,
                'PERFIL_CLIENTE_ATIVADO',
                'Usuário existente ativou o perfil de cliente.',
                'usuario:' . $cpf,
                $cpf
            );

            $mensagem_sucesso = 'Perfil de cliente ativado! Agora você também pode entrar como cliente.';
            $modo_atual = 'login';
        }
    } catch (Throwable $excecao) {
        $mensagem_erro = $excecao->getMessage();
    }
}

renderizar_cabecalho('Login - Cliente', null);
?>

<section class="tela-login">
    <div class="login-cliente-container">
        
        <div class="login-cliente-cabecalho">
            <h1 class="login-cliente-marca">DECOLAE</h1>
            <p class="login-cliente-subtitulo">Login de Cliente</p>
        </div>
        
        <div class="cartao-login">
            
            <?php if ($mensagem_erro): ?>
                <div class="aviso aviso-login-erro">
                    <?= esc($mensagem_erro) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($mensagem_sucesso): ?>
                <div class="aviso aviso-login-sucesso">
                    <?= esc($mensagem_sucesso) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($modo_atual === 'login'): ?>
                
                <h2 class="titulo-login">Faça seu login</h2>
                <p class="descricao-login">Entre com seu email e senha</p>
                
                <form method="post">
                    <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
                    <input type="hidden" name="modo" value="login">
                    
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
                
                <div class="alternar-modo-login">
                    <p class="alternar-modo-login-texto">Não tem conta ainda?</p>
                    <a class="link-botao-login" href="login-cliente.php?modo=cadastro">
                        Criar nova conta
                    </a>
                </div>
            
            <?php elseif ($modo_atual === 'ativar'): ?>

                <div class="flex gap-2" style="margin-bottom: 15px;">
                    <a class="botao-pequeno " href="login-cliente.php?modo=cadastro">Nova conta</a>
                    <a class="botao-pequeno aba-ativa" href="login-cliente.php?modo=ativar">Já tenho conta de funcionário</a>
                </div>

                <h2 class="titulo-login">Já tenho conta de funcionário</h2>
                <p class="descricao-login">Use os dados da sua conta para também entrar como cliente</p>

                <form method="post">
                    <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
                    <input type="hidden" name="modo" value="ativar">

                    <div class="campo-login-form">
                        <label class="rotulo-login">CPF</label>
                        <input
                            name="cpf"
                            type="text"
                            placeholder="000.000.000-00"
                            maxlength="14"
                            required
                            class="entrada-login"
                        >
                    </div>

                    <div class="campo-login-form">
                        <label class="rotulo-login">Email</label>
                        <input
                            name="email_ativacao"
                            type="email"
                            placeholder="seu@email.com"
                            required
                            class="entrada-login"
                        >
                    </div>

                    <div class="campo-login-form campo-login-form-final">
                        <label class="rotulo-login">Senha</label>
                        <input
                            name="senha_ativacao"
                            type="password"
                            placeholder="••••••••"
                            required
                            class="entrada-login"
                        >
                    </div>

                    <button class="botao-login botao-login-cadastro" type="submit">
                        Ativar conta de cliente
                    </button>
                </form>

                <div class="alternar-modo-login">
                    <p class="alternar-modo-login-texto">Já tem conta de cliente?</p>
                    <a class="link-botao-login" href="login-cliente.php">
                        Fazer login
                    </a>
                </div>

            <?php else: ?>
                
                <div class="flex gap-2" style="margin-bottom: 15px;">
                    <a class="botao-pequeno aba-ativa" href="login-cliente.php?modo=cadastro">Nova conta</a>
                    <a class="botao-pequeno " href="login-cliente.php?modo=ativar">Já tenho conta de funcionário</a>
                </div>

                <h2 class="titulo-login">Criar nova conta</h2>
                <p class="descricao-login">Preencha os dados abaixo</p>
                
                <form method="post">
                    <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
                    <input type="hidden" name="modo" value="cadastro">
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Nome completo</label>
                        <input 
                            name="nome_completo" 
                            type="text" 
                            placeholder="Seu nome completo" 
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">CPF</label>
                        <input 
                            name="cpf" 
                            type="text" 
                            placeholder="000.000.000-00" 
                            maxlength="14"
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Email</label>
                        <input 
                            name="email_cadastro" 
                            type="email" 
                            placeholder="seu@email.com" 
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Telefone</label>
                        <input 
                            name="telefone" 
                            type="tel" 
                            placeholder="(11) 9999-9999" 
                            maxlength="15"
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Endereço</label>
                        <input 
                            name="endereco" 
                            type="text"
                            maxlength="255"
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form">
                        <label class="rotulo-login">Senha</label>
                        <input 
                            name="senha_cadastro" 
                            type="password" 
                            placeholder="••••••••" 
                            minlength="6"
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <div class="campo-login-form campo-login-form-final">
                        <label class="rotulo-login">Confirmar Senha</label>
                        <input 
                            name="confirmacao_senha" 
                            type="password" 
                            placeholder="••••••••" 
                            minlength="6"
                            required
                            class="entrada-login"
                        >
                    </div>
                    
                    <button class="botao-login botao-login-cadastro" type="submit">
                        Criar Conta
                    </button>
                </form>
                
                <div class="alternar-modo-login">
                    <p class="alternar-modo-login-texto">Já tem conta?</p>
                    <a class="link-botao-login" href="login-cliente.php">
                        Fazer login
                    </a>
                </div>
            
            <?php endif; ?>
        
        </div>
    </div>
</section>

<div class="rodape-login">
    <p>
        É gerente, administrador ou tripulante? 
        <a href="login-administrativo.php">Acesse aqui</a>
    </p>
</div>

<?php renderizar_rodape(); ?>
