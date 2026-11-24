<?php


declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();

// Só o perfil de tripulante pode consultar esta página.
$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino,
            ROUND(MOD(TIME_TO_SEC(t.h_chegada) - TIME_TO_SEC(t.h_partida) + 86400, 86400) / 3600, 2) AS horas_trabalhadas
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
     WHERE e.tripulante_cpf = ?
     ORDER BY v.data_voo DESC, t.h_partida DESC"
);
$comando->execute([$usuario_logado['cpf']]);
$voos = $comando->fetchAll();

$total_horas = array_sum(array_map(static fn(array $voo): float => (float) $voo['horas_trabalhadas'], $voos));

function formatar_horas(float $horas): string
{
    return number_format($horas, 2, ',', '.') . ' h';
}

renderizar_cabecalho('Horas trabalhadas', $usuario_logado);
?>

<div class="pagina-tripulante">
  <a class="botao-pequeno" href="tripulante.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina titulo-pagina-compacto">Horas Trabalhadas</h1>

  <?php if ($voos): ?>
    <div class="cartao-formulario" style="overflow-x: auto;">
      <table class="tabela-operacional">
        <thead>
          <tr>
            <th>Data</th>
            <th>Voo</th>
            <th>Origem</th>
            <th>Destino</th>
            <th>Horas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos as $voo): ?>
            <tr>
              <td><?= esc((new DateTime($voo['data_voo']))->format('d/m/Y')) ?></td>
              <td><?= esc($voo['numero_voo']) ?></td>
              <td><?= esc($voo['origem']) ?></td>
              <td><?= esc($voo['destino']) ?></td>
              <td><?= esc(formatar_horas((float) $voo['horas_trabalhadas'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4">Total</th>
            <th><?= esc(formatar_horas($total_horas)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php else: ?>
    <div class="estado-vazio-escala">
      <p>Nenhuma hora registrada.</p>
    </div>
  <?php endif; ?>
</div>

<?php renderizar_rodape(); ?>
