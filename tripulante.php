<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$conexao_pdo = db();

$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino, t.h_partida, t.h_chegada,
            a.modelo, a.identificacao, e.funcao_tripulante
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
     JOIN aeronave a ON a.id = v.aeronave_id
     WHERE e.tripulante_cpf = ?
       AND TIMESTAMP(v.data_voo, t.h_partida) > NOW()
     ORDER BY v.data_voo, t.h_partida"
);

$comando->execute([$usuario_logado['cpf']]);

$escalas_tripulante = $comando->fetchAll();

renderizar_cabecalho('Próximas escalas', $usuario_logado);
?>

<div class="pagina-tripulante">
  <h1 class="titulo-pagina titulo-pagina-compacto">Próximas Escalas</h1>
  <p class="descricao-pagina">
    Olá, <?= esc($usuario_logado['nome_completo']) ?>. Aqui estão seus próximos voos.
  </p>
  
  <?php if ($escalas_tripulante): ?>
    <div class="grade-escalas">
      <?php foreach ($escalas_tripulante as $escala): ?>
        <div class="cartao-escala">
          
          <div class="cartao-escala-cabecalho">
            <div>
              <div class="escala-funcao">
                <?= esc($escala['funcao_tripulante']) ?>
              </div>
              <div class="escala-numero">
                <?= esc($escala['numero_voo']) ?>
              </div>
            </div>
            <span class="escala-status">
              ● Escalado
            </span>
          </div>
          
          <div class="horarios-escala">
            <div>
              <div class="horario-escala-valor">
                <?= esc(substr($escala['h_partida'], 0, 5)) ?>
              </div>
              <div class="horario-escala-local">
                <?= esc($escala['origem']) ?>
              </div>
            </div>
            
            <div class="linha-escala"></div>
            
            <div class="horario-escala-destino">
              <div class="horario-escala-valor">
                <?= esc(substr($escala['h_chegada'], 0, 5)) ?>
              </div>
              <div class="horario-escala-local">
                <?= esc($escala['destino']) ?>
              </div>
            </div>
          </div>
          
          <div class="detalhes-aeronave">
            <strong><?= esc((new DateTime($escala['data_voo']))->format('d/m/Y')) ?></strong>
            · <?= esc($escala['modelo']) ?>
            · <?= esc($escala['identificacao']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="estado-vazio-escala">
      <p>Nenhuma escala futura para este tripulante.</p>
    </div>
  <?php endif; ?>

  <div class="grade-formularios" style="margin-top: 30px;">
    <a class="cartao-formulario" href="tripulante-historico.php">
      <h2 class="titulo-formulario">Histórico de Voos</h2>
      <p class="texto-pequeno" style="color: #000; font-size: 15px;">Consulte todos os voos em que você foi escalado.</p>
    </a>

    <a class="cartao-formulario" href="tripulante-horas.php">
      <h2 class="titulo-formulario">Horas Trabalhadas</h2>
      <p class="texto-pequeno" style="color: #000; font-size: 15px;">Veja quantas horas você trabalhou em cada voo.</p>
    </a>
  </div>
</div>

<?php renderizar_rodape(); ?>

