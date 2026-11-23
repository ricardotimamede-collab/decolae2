<?php

/**
 * Aqui a gente mostra as horas trabalhadas em cada voo de quem está conectado como tripulante.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();

// Só o perfil de tripulante pode consultar esta página.
$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

// A duração considera também os voos que chegam no dia seguinte.
$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino,
            ROUND(MOD(TIME_TO_SEC(t.h_chegada) - TIME_TO_SEC(t.h_partida) + 86400, 86400) / 3600, 2) AS horas_trabalhadas
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.id = v.trajeto_id
     WHERE e.tripulante_cpf = ?
     ORDER BY v.data_voo DESC, t.h_partida DESC"
);
$comando->execute([$usuario_logado['cpf']]);
$voos = $comando->fetchAll();

$total_horas = array_sum(array_map(static fn(array $voo): float => (float) $voo['horas_trabalhadas'], $voos));

/**
 * Aqui a gente mostra as horas no formato brasileiro, como "2,50 h".
 */
function formatar_horas(float $horas): string
{
    return number_format($horas, 2, ',', '.') . ' h';
}

renderizar_cabecalho('Horas trabalhadas', $usuario_logado);
?>

<div class="pagina-tripulante">
  <a class="secondary-btn px-4 py-2" href="tripulante.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina titulo-pagina-compacto">Horas Trabalhadas</h1>

  <?php if ($voos): ?>
    <div class="cartao-formulario" style="overflow-x: auto;">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-200">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Voo</th>
            <th class="py-2 pr-4">Origem</th>
            <th class="py-2 pr-4">Destino</th>
            <th class="py-2">Horas</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos as $voo): ?>
            <tr class="border-b border-slate-100">
              <td class="py-2 pr-4"><?= esc((new DateTime($voo['data_voo']))->format('d/m/Y')) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['numero_voo']) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['origem']) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['destino']) ?></td>
              <td class="py-2"><?= esc(formatar_horas((float) $voo['horas_trabalhadas'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="py-3 font-bold" colspan="4">Total</td>
            <td class="py-3 font-bold"><?= esc(formatar_horas($total_horas)) ?></td>
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
