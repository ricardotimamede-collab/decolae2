<?php

/**
 * Aqui a gente mostra a escala de quem está conectado como tripulante.
 * 
 * A lista reúne voo, data, horários, rota, aeronave e função.
 */

declare(strict_types=1);

// Aqui a gente carrega as funções e o layout usados nesta tela.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Primeiro, a gente confirma que a pessoa está no perfil de tripulante.

// A conexão vai buscar os voos escalados.
$conexao_pdo = db();

// Só o perfil de tripulante pode consultar esta página.
$usuario_logado = exigir_papel($conexao_pdo, 'tripulante');

// Agora a gente busca a escala da pessoa conectada.

/**
 * Aqui a gente reúne os voos e os detalhes necessários para exibir a escala.
 * 
 * Os dados vêm do voo, do trajeto, da aeronave e da função escalada.
 */
$comando = $conexao_pdo->prepare(
    "SELECT v.numero_voo, v.data_voo, t.origem, t.destino, t.h_partida, t.h_chegada,
            a.modelo, a.identificacao, e.funcao_tripulante
     FROM escala e
     JOIN voo v ON v.id = e.voo_id
     JOIN trajeto t ON t.id = v.trajeto_id
     JOIN aeronave a ON a.id = v.aeronave_id
     WHERE e.tripulante_cpf = ?
       AND TIMESTAMP(v.data_voo, t.h_partida) > NOW()
     ORDER BY v.data_voo, t.h_partida"
);

// A consulta usa o CPF da pessoa conectada.
$comando->execute([$usuario_logado['cpf']]);

// Aqui a gente guarda os resultados para montar a lista.
$escalas_tripulante = $comando->fetchAll();

// A partir daqui a gente monta a tela da escala.
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
          
          <!-- Aqui aparecem os dados principais do voo. -->
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
          
          <!-- Neste bloco a gente mostra os horários do trajeto. -->
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
          
          <!-- Aqui ficam os detalhes finais da escala. -->
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

  <!-- Estes cartões levam para as outras consultas do tripulante. -->
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

