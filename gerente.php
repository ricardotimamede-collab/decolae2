<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexaoBancoDados = db();
$usuarioLogado = require_role($conexaoBancoDados, 'gerente');

$total_trajetos = (int) $conexaoBancoDados->query('SELECT COUNT(*) FROM trajeto')->fetchColumn();
$total_aeronaves = (int) $conexaoBancoDados->query('SELECT COUNT(*) FROM aeronave')->fetchColumn();
$total_tripulantes = (int) $conexaoBancoDados->query('SELECT COUNT(*) FROM tripulante')->fetchColumn();

renderizar_cabecalho('Gerência', $usuarioLogado);
?>

<div class="pagina-gerencia">
  <h1 class="titulo-pagina">Gerência</h1>
  <p class="descricao-pagina">
    Bem-vindo, <?= esc($usuarioLogado['nome_completo']) ?>. Gerencie trajetos, aeronaves e tripulação da Decolae.
  </p>

  <div class="grade-formularios">
    <a class="cartao-gerencia" href="gerente-trajetos.php">
      <h2 class="cartao-gerencia-titulo">Trajetos</h2>
      <p class="cartao-gerencia-total"><?= $total_trajetos ?> <?= $total_trajetos === 1 ? 'cadastrado' : 'cadastrados' ?></p>
    </a>

    <a class="cartao-gerencia" href="gerente-aeronaves.php">
      <h2 class="cartao-gerencia-titulo">Aeronaves</h2>
      <p class="cartao-gerencia-total"><?= $total_aeronaves ?> <?= $total_aeronaves === 1 ? 'cadastrada' : 'cadastradas' ?></p>
    </a>

    <a class="cartao-gerencia" href="gerente-tripulacao.php">
      <h2 class="cartao-gerencia-titulo">Tripulação</h2>
      <p class="cartao-gerencia-total"><?= $total_tripulantes ?> <?= $total_tripulantes === 1 ? 'cadastrado' : 'cadastrados' ?></p>
    </a>
  </div>
</div>
<?php renderizar_rodape(); ?>
