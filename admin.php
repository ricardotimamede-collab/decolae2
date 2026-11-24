<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();
$usuario_logado = exigir_papel($conexao_pdo, 'admin');

$total_voos = (int) $conexao_pdo->query('SELECT COUNT(*) FROM voo')->fetchColumn();
$total_logs = (int) $conexao_pdo->query('SELECT COUNT(*) FROM log')->fetchColumn();

renderizar_cabecalho('Administração', $usuario_logado);
?>

<div class="pagina-administracao">
  <h1 class="titulo-pagina">Administração</h1>
  <p class="descricao-pagina">
    Bem-vindo, <?= esc($usuario_logado['nome_completo']) ?>. Gerencie os voos e consulte os logs da Decolae.
  </p>

  <div class="grade-formularios">
    <a class="cartao-gerencia" href="admin-voos.php">
      <h2 class="cartao-gerencia-titulo">Operação de Voos</h2>
      <p class="cartao-gerencia-total"><?= $total_voos ?> <?= $total_voos === 1 ? 'voo' : 'voos' ?></p>
    </a>

    <a class="cartao-gerencia" href="admin-logs.php">
      <h2 class="cartao-gerencia-titulo">Logs do Sistema</h2>
      <p class="cartao-gerencia-total"><?= $total_logs ?> <?= $total_logs === 1 ? 'registro' : 'registros' ?></p>
    </a>
  </div>
</div>
<?php renderizar_rodape(); ?>
