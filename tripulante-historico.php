<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();

$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino, t.h_partida, t.h_chegada,
            e.funcao_tripulante
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
     WHERE e.tripulante_cpf = ?
     ORDER BY v.data_voo DESC, t.h_partida DESC"
);
$comando->execute([$usuario_logado['cpf']]);
$voos = $comando->fetchAll();

$nomes_funcoes = ['piloto' => 'Piloto', 'copiloto' => 'Copiloto', 'comissario' => 'Comissário'];

renderizar_cabecalho('Histórico de voos', $usuario_logado);
?>

<div class="pagina-tripulante">
  <a class="botao-pequeno" href="tripulante.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina titulo-pagina-compacto">Histórico de Voos</h1>

  <?php if ($voos): ?>
    <div class="cartao-formulario" style="overflow-x: auto;">
      <table class="tabela-operacional">
        <thead>
          <tr>
            <th>Data</th>
            <th>Voo</th>
            <th>Origem</th>
            <th>Destino</th>
            <th>Horário</th>
            <th>Função</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos as $voo): ?>
            <tr>
              <td><?= esc((new DateTime($voo['data_voo']))->format('d/m/Y')) ?></td>
              <td><?= esc($voo['numero_voo']) ?></td>
              <td><?= esc($voo['origem']) ?></td>
              <td><?= esc($voo['destino']) ?></td>
              <td><?= esc(substr($voo['h_partida'], 0, 5)) ?> – <?= esc(substr($voo['h_chegada'], 0, 5)) ?></td>
              <td><?= esc($nomes_funcoes[$voo['funcao_tripulante']] ?? $voo['funcao_tripulante']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="estado-vazio-escala">
      <p>Nenhum voo no seu histórico.</p>
    </div>
  <?php endif; ?>
</div>

<?php renderizar_rodape(); ?>
