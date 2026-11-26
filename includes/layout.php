<?php

declare(strict_types=1);

function renderizar_cabecalho(string $titulo, ?array $usuario = null): void
{
    $token_csrf = obter_token_csrf();
    
    $papeis = $usuario['papeis'] ?? [];
    
    $papel_ativo = $usuario['papel_atual'] ?? null;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <meta name="csrf-token" content="<?= esc($token_csrf) ?>">
    
    <meta name="description" content="DECOLAE — encontre seu próximo destino com praticidade.">
    <title><?= esc($titulo) ?> · DECOLAE</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>">
</head>
<body class="bg-[#f7faff] text-ink antialiased">

<header class="sticky top-0 z-30 border-b border-white/60 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
        <a href="index.php" class="flex items-center gap-3" aria-label="Página inicial">
            <span class="brand-mark">✦</span>
            <span>
                <span class="brand-name">DECOLAE</span>
                <span class="hidden text-[10px] font-semibold uppercase tracking-[.22em] text-slate-400 sm:block">
                    conectando destinos
                </span>
            </span>
        </a>
        
        <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-500 md:flex">
            <?php if (!$usuario || $papel_ativo === 'cliente'): ?>
                <?php $pagina_atual = basename($_SERVER['SCRIPT_NAME']); ?>
                <a class="nav-link <?= $pagina_atual !== 'meus-bilhetes.php' ? 'active' : '' ?>" href="index.php#voos">Encontrar voos</a>
                <a class="nav-link <?= $pagina_atual === 'meus-bilhetes.php' ? 'active' : '' ?>" href="meus-bilhetes.php">Meus Bilhetes</a>
            <?php endif; ?>
            
            <?php if ($papel_ativo === 'gerente'): ?>
                <a class="nav-link active" href="gerente.php">Gerência</a>
            <?php endif; ?>
            
            <?php if ($papel_ativo === 'admin'): ?>
                <a class="nav-link active" href="admin.php">Administração</a>
            <?php endif; ?>
            
            <?php if ($papel_ativo === 'tripulante'): ?>
                <a class="nav-link active" href="tripulante.php">Minha escala</a>
            <?php endif; ?>
        </nav>
        
        <div class="flex items-center gap-3">
            <?php if ($usuario): ?>
                <div class="hidden text-right sm:block">
                    <p class="text-xs font-bold text-ink">
                        <?= esc($usuario['nome_completo']) ?>
                    </p>
                    <p class="text-[11px] text-slate-400">
                        perfil ativo: <?= esc($papel_ativo ?? '—') ?>
                    </p>
                </div>
                
                <a class="secondary-btn botao-perigo px-4 py-2" href="logout.php">↗ Sair</a>
            <?php elseif (!in_array(basename($_SERVER['SCRIPT_NAME']), ['login-cliente.php', 'login-administrativo.php'], true)): ?>
                <a href="login-cliente.php" class="secondary-btn px-4 py-2">
                    Entrar
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
<?php
}

function render_header(string $titulo, ?array $usuario = null): void
{
    renderizar_cabecalho($titulo, $usuario);
}

function renderizar_rodape(): void
{
    ?>
</main>

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

<div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-50 hidden -translate-x-1/2 rounded-2xl px-5 py-3 text-sm font-bold shadow-2xl"></div>

<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>

</body>
</html>
<?php
}

function render_footer(): void
{
    renderizar_rodape();
}
