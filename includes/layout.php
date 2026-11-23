<?php

/**
 * Aqui a gente monta as partes de layout que várias páginas compartilham.
 * 
 * O cabeçalho cuida da navegação; o rodapé carrega os avisos e scripts.
 */

declare(strict_types=1);

/**
 * Aqui a gente monta o cabeçalho com os dados da página e da pessoa conectada.
 * 
 * A navegação muda conforme o perfil ativo.
 * 
 * @param string $titulo Título da página (exibido na aba do navegador)
 * @param array|null $usuario Array com dados do usuário logado (opcional)
 * @return void
 */
function renderizar_cabecalho(string $titulo, ?array $usuario = null): void
{
    // A gente inclui o token para proteger os formulários.
    $token_csrf = obter_token_csrf();
    
    // Esses perfis definem quais opções aparecem no menu.
    $papeis = $usuario['papeis'] ?? [];
    
    // Esse é o perfil que a pessoa está usando agora.
    $papel_ativo = $usuario['papel_atual'] ?? null;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <!-- Aqui a gente prepara a página para diferentes telas e caracteres. -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- O JavaScript usa esse token nas requisições protegidas. -->
    <meta name="csrf-token" content="<?= esc($token_csrf) ?>">
    
    <!-- Aqui ficam a descrição e o título que aparecem no navegador. -->
    <meta name="description" content="DECOLAE — encontre seu próximo destino com praticidade.">
    <title><?= esc($titulo) ?> · DECOLAE</title>
    
    <!-- A gente carrega o Tailwind para montar o visual das páginas. -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Essas cores e sombras são as que a gente usa no tema do site. -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#10203a',
                        navy: '#102d52',
                        ocean: '#0d6efd',
                        sky: '#eaf5ff',
                        coral: '#ff6b4a',
                        mint: '#dff8ee'
                    },
                    boxShadow: {
                        soft: '0 20px 60px rgba(16,45,82,.12)'
                    }
                }
            }
        }
    </script>
    
    <!-- Aqui a gente carrega as fontes usadas na interface. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Os estilos próprios do projeto ficam neste arquivo. -->
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
</head>
<body class="bg-[#f7faff] text-ink antialiased">

<!-- Aqui começa o menu principal, que fica visível no topo ao rolar a página. -->
<header class="sticky top-0 z-30 border-b border-white/60 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
        <!-- A marca leva de volta para a página inicial. -->
        <a href="index.php" class="flex items-center gap-3" aria-label="Página inicial">
            <span class="brand-mark">✦</span>
            <span>
                <span class="brand-name">DECOLAE</span>
                <span class="hidden text-[10px] font-semibold uppercase tracking-[.22em] text-slate-400 sm:block">
                    conectando destinos
                </span>
            </span>
        </a>
        
        <!-- Aqui a gente mostra os links disponíveis para cada perfil. -->
        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-500 md:flex">
            <!-- Quem está na área de cliente pode buscar voos e ver bilhetes. -->
            <?php if (!$usuario || $papel_ativo === 'cliente'): ?>
                <?php $pagina_atual = basename($_SERVER['SCRIPT_NAME']); ?>
                <a class="nav-link <?= $pagina_atual !== 'meus-bilhetes.php' ? 'active' : '' ?>" href="index.php#voos">Encontrar voos</a>
                <a class="nav-link <?= $pagina_atual === 'meus-bilhetes.php' ? 'active' : '' ?>" href="meus-bilhetes.php">Meus Bilhetes</a>
            <?php endif; ?>
            
            <!-- O gerente acessa as ferramentas de cadastro por aqui. -->
            <?php if ($papel_ativo === 'gerente'): ?>
                <a class="nav-link active" href="gerente.php">Gerência</a>
            <?php endif; ?>
            
            <!-- O administrador acessa a operação dos voos por aqui. -->
            <?php if ($papel_ativo === 'admin'): ?>
                <a class="nav-link active" href="admin.php">Administração</a>
            <?php endif; ?>
            
            <!-- O tripulante encontra a própria escala neste link. -->
            <?php if ($papel_ativo === 'tripulante'): ?>
                <a class="nav-link active" href="tripulante.php">Minha escala</a>
            <?php endif; ?>
        </nav>
        
        <!-- Aqui a gente mostra a conta conectada ou o botão para entrar. -->
        <div class="flex items-center gap-3">
            <?php if ($usuario): ?>
                <!-- Com sessão ativa, mostramos o nome e o perfil selecionado. -->
                <div class="hidden text-right sm:block">
                    <p class="text-xs font-bold text-ink">
                        <?= esc($usuario['nome_completo']) ?>
                    </p>
                    <p class="text-[11px] text-slate-400">
                        perfil ativo: <?= esc($papel_ativo ?? '—') ?>
                    </p>
                </div>
                
                <!-- Este botão encerra a sessão. -->
                <a class="secondary-btn px-4 py-2" href="logout.php">↗ Sair</a>
            <?php elseif (!in_array(basename($_SERVER['SCRIPT_NAME']), ['login-cliente.php', 'login-administrativo.php'], true)): ?>
                <!-- Sem sessão, a pessoa pode entrar por aqui, exceto nas telas de login. -->
                <a href="login-cliente.php" class="secondary-btn px-4 py-2">
                    Entrar
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- O conteúdo específico de cada página entra aqui. -->
<main>
<?php
}

/**
 * Esse nome antigo continua disponível para as páginas que ainda o chamam.
 * 
 * @param string $titulo Título da página
 * @param array|null $usuario Dados do usuário
 * @return void
 */
function render_header(string $titulo, ?array $usuario = null): void
{
    renderizar_cabecalho($titulo, $usuario);
}

/**
 * Aqui a gente fecha a página com o rodapé e os scripts compartilhados.
 * 
 * O elemento de toast fica pronto para receber avisos da interface.
 * 
 * @return void
 */
function renderizar_rodape(): void
{
    ?>
</main>

<!-- Aqui termina a área principal e começa o rodapé. -->
<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">
        <p>
            <span class="brand-name">DECOLAE</span>
            <span class="block text-[10px] font-semibold uppercase tracking-[.22em] text-slate-400">
                conectando destinos
            </span>
        </p>
    </div>
</footer>

<!-- O JavaScript usa este espaço para mostrar notificações rápidas. -->
<div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 rounded-2xl px-5 py-3 text-sm font-bold shadow-2xl"></div>

<!-- Aqui a gente carrega os comportamentos usados nas telas. -->
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>

</body>
</html>
<?php
}

/**
 * Esse nome antigo continua disponível para as páginas que ainda o chamam.
 * 
 * @return void
 */
function render_footer(): void
{
    renderizar_rodape();
}
