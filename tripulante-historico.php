<?php

/**
 * Aqui a gente mostra o histórico de voos de quem está conectado como tripulante.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();

// Só o perfil de tripulante pode consultar esta página.
$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

// Os voos mais recentes aparecem primeiro.
$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino, t.h_partida, t.h_chegada,
            e.funcao_tripulante
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.id = v.trajeto_id
     WHERE e.tripulante_cpf = ?
     ORDER BY v.data_voo DESC, t.h_partida DESC"
);
$comando->execute([$usuario_logado['cpf']]);
$voos = $comando->fetchAll();

$nomes_funcoes = ['piloto' => 'Piloto', 'copiloto' => 'Copiloto', 'comissario' => 'Comissário'];

renderizar_cabecalho('Histórico de voos', $usuario_logado);
?>

<div class="pagina-tripulante">
  <a class="secondary-btn px-4 py-2" href="tripulante.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina titulo-pagina-compacto">Histórico de Voos</h1>

  <?php if ($voos): ?>
    <div class="cartao-formulario" style="overflow-x: auto;">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-slate-200">
            <th class="py-2 pr-4">Data</th>
            <th class="py-2 pr-4">Voo</th>
            <th class="py-2 pr-4">Origem</th>
            <th class="py-2 pr-4">Destino</th>
            <th class="py-2 pr-4">Horário</th>
            <th class="py-2">Função</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos as $voo): ?>
            <tr class="border-b border-slate-100">
              <td class="py-2 pr-4"><?= esc((new DateTime($voo['data_voo']))->format('d/m/Y')) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['numero_voo']) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['origem']) ?></td>
              <td class="py-2 pr-4"><?= esc($voo['destino']) ?></td>
              <td class="py-2 pr-4"><?= esc(substr($voo['h_partida'], 0, 5)) ?> – <?= esc(substr($voo['h_chegada'], 0, 5)) ?></td>
              <td class="py-2"><?= esc($nomes_funcoes[$voo['funcao_tripulante']] ?? $voo['funcao_tripulante']) ?></td>
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
