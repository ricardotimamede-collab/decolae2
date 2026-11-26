<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

$conexao_pdo = null;
$usuario = null;
$voos = [];
$erro_banco = null;
$aviso = obter_aviso();

try {
    $conexao_pdo = db();
    
    $usuario = obter_usuario_atual($conexao_pdo);

    $comando = $conexao_pdo->query(
        "SELECT v.id, v.numero_voo, v.data_voo, v.status, v.valor_base, v.aeronave_id,
                t.origem, t.destino, t.h_partida, t.h_chegada,
                CONCAT(v.data_voo, ' ', t.h_partida) AS partida_em,
                TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY AS chegada_em,
                a.modelo, a.identificacao,
                (a.fileiras * a.assentos_por_fileira) AS capacidade,
                COALESCE((a.fileiras * a.assentos_por_fileira) - (
                    SELECT COUNT(*) FROM passagem 
                    WHERE voo_id = v.id AND status IN ('confirmada', 'checkin')
                ), a.fileiras * a.assentos_por_fileira) AS assentos_livres
         FROM voo v
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
         JOIN aeronave a ON a.id = v.aeronave_id
         WHERE v.status <> 'cancelado' AND v.data_voo >= CURDATE()
           AND v.id IN (
               SELECT voo_id FROM escala GROUP BY voo_id
               HAVING SUM(funcao_tripulante = 'piloto') >= 1
                  AND SUM(funcao_tripulante = 'copiloto') >= 1
                  AND SUM(funcao_tripulante = 'comissario') >= 2
           )
         ORDER BY partida_em LIMIT 12"
    );
    $voos = $comando->fetchAll();
} catch (Throwable $e) {
    $erro_banco = $e->getMessage();
}

renderizar_cabecalho('Encontre seu próximo destino', $usuario);
?>

<div id="voos" class="secao-voos">
  <?php if ($aviso && $aviso['tipo'] === 'erro'): ?>
    <div class="aviso aviso-erro"><?= esc($aviso['texto']) ?></div>
  <?php endif; ?>
  
  <div class="cartao-busca-voos">
    <h2 class="titulo-secao-busca">Buscar Voos</h2>
    
    <form id="search-form" class="formulario-busca-voos">
      <div>
        <label class="rotulo-busca-voos" for="busca-origem">Origem</label>
        <input type="search" class="entrada-busca-voos" id="busca-origem" name="origin" placeholder="Cidade" maxlength="60" autocomplete="off">
      </div>
      
      <div>
        <label class="rotulo-busca-voos" for="busca-destino">Destino</label>
        <input type="search" class="entrada-busca-voos" id="busca-destino" name="destination" placeholder="Cidade" maxlength="60" autocomplete="off">
      </div>
      
      <div>
        <label class="rotulo-busca-voos">Data do voo</label>
        <input class="entrada-busca-voos" type="date" name="date">
      </div>

      <div class="container-botao-busca">
        <button class="botao-busca-voos" type="submit">Buscar</button>
      </div>
    </form>
  </div>
  
  <?php if ($erro_banco): ?>
    <div class="aviso aviso-banco">
      <strong>Aviso:</strong> Banco de dados não configurado.
    </div>
  <?php else: ?>
    
    <h2 class="titulo-lista-voos">Voos Próximos</h2>
    
    <div id="flight-list" class="lista-voos">
      <?php foreach ($voos as $voo): ?>
        <?= card_voo_html($voo) ?>
      <?php endforeach; ?>
    </div>
    
    <div id="empty-state" class="estado-vazio-voos" hidden>
      <p class="estado-vazio-voos-titulo">Nenhum voo encontrado</p>
      <p>Confira o código IATA e a data escolhida.</p>
    </div>
  
  <?php endif; ?>
</div>

<?php

function card_voo_html(array $voo): string
{
    $data_partida = new DateTime($voo['partida_em']);
    $data_chegada = new DateTime($voo['chegada_em']);
    $diferenca_tempo = $data_partida->diff($data_chegada);
    $duracao_formatada = $diferenca_tempo->h . 'h ' . str_pad((string) $diferenca_tempo->i, 2, '0', STR_PAD_LEFT) . 'm';
    
    $assentos_livres = (int) $voo['assentos_livres'];
    $capacidade_total = (int) $voo['capacidade'];
    ob_start();
    ?>
    <div class="flight-card-simples cartao-resultado-voo"
         data-flight-card
         data-flight-id="<?= (int) $voo['id'] ?>"
         data-origin="<?= esc($voo['origem']) ?>"
         data-destination="<?= esc($voo['destino']) ?>"
         data-date="<?= esc($voo['data_voo']) ?>">
      <div class="cabecalho-resultado-voo">
        <div>
          <p class="rotulo-resultado-voo">Voo <?= esc($voo['numero_voo']) ?></p>
          <p class="modelo-resultado-voo"><?= esc($voo['modelo']) ?></p>
        </div>
        <div class="status-resultado-voo">
          <p class="rotulo-resultado-voo">Status</p>
          <p class="valor-status-resultado-voo"><?= esc($voo['status']) ?></p>
        </div>
        <div class="assentos-resultado-voo">
          <p class="rotulo-resultado-voo">Assentos</p>
          <p class="valor-assentos-resultado-voo"><?= $assentos_livres ?>/<?= $capacidade_total ?></p>
        </div>
      </div>

      <div class="horarios-resultado-voo">
        <div>
          <p class="rotulo-resultado-voo">Origem</p>
          <p class="local-resultado-voo"><?= esc($voo['origem']) ?></p>
          <p class="data-resultado-voo"><?= $data_partida->format('d/m/Y') ?></p>
          <p class="horario-resultado-voo"><?= $data_partida->format('H:i') ?></p>
        </div>
        <div class="duracao-resultado-voo">
          <p class="rotulo-resultado-voo">Duração</p>
          <p class="data-resultado-voo"><?= esc($duracao_formatada) ?></p>
          <p class="seta-resultado-voo">→</p>
        </div>
        <div>
          <p class="rotulo-resultado-voo">Destino</p>
          <p class="local-resultado-voo"><?= esc($voo['destino']) ?></p>
          <p class="data-resultado-voo"><?= $data_chegada->format('d/m/Y') ?></p>
          <p class="horario-resultado-voo"><?= $data_chegada->format('H:i') ?></p>
        </div>
      </div>

      <div class="acoes-resultado-voo">
        <div>
          <p class="rotulo-resultado-voo">A partir de</p>
          <p class="preco-resultado-voo"><?= formatar_moeda_brl($voo['valor_base']) ?></p>
        </div>
        <button class="select-flight-btn botao-resultado-voo" data-flight-id="<?= (int) $voo['id'] ?>">Escolher Assento →</button>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

?>

<div id="seat-modal" class="modal-assentos" hidden>
  <div class="painel-modal-assentos">
    
    <div class="cabecalho-modal-assentos">
      <div>
        <h3 id="modal-title" class="titulo-modal-assentos">Selecionar Assento</h3>
        <p id="modal-subtitle" class="subtitulo-modal-assentos"></p>
      </div>
      <button id="close-modal" class="botao-fechar-modal">✕</button>
    </div>
    
    <div class="corpo-modal-assentos">
      <div id="seat-map" class="mapa-assentos">
      </div>
      
      <div class="legenda-assentos">
        <span>□ Livre</span>
        <span>■ Selecionado</span>
        <span>◼ Ocupado</span>
      </div>
    </div>
    
    <div class="resumo-modal-assentos">
      <div id="booking-summary" class="conteudo-resumo-assentos">
        <p class="texto-resumo-vazio">Selecione um assento</p>
      </div>
    </div>
    
    <div class="acoes-modal-assentos">
      <button id="cancel-modal" class="botao-cancelar-modal">
        Cancelar
      </button>
      <button id="confirm-booking" class="botao-confirmar-modal" disabled>
        Continuar
      </button>
    </div>
  </div>
</div>

<div id="toast" class="aviso-flutuante" hidden></div>

<?php
renderizar_rodape();
