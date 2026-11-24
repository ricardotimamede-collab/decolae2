<?php

declare(strict_types=1);

// Aqui a gente carrega as funções compartilhadas e o layout da página.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Esta área é exclusiva para quem entrou como cliente.

// Sem uma sessão de cliente, a gente volta para a tela de login.
if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

// Aqui ficam os dados e avisos usados pela página.
$conexao_pdo = null;
$usuario = null;
$voos = [];
$codigos_aeroportos = [];
$erro_banco = null;
$aviso = obter_aviso();

try {
    // A gente usa esta conexão para consultar os voos.
    $conexao_pdo = db();
    
    // Primeiro carregamos os dados da conta conectada.
    $usuario = obter_usuario_atual($conexao_pdo);

    // Essas opções ajudam a pessoa a escolher uma rota e uma data disponíveis.
    $codigos_aeroportos = $conexao_pdo->query(
        "SELECT codigo
         FROM (
             SELECT t.origem AS codigo
             FROM voo v
             JOIN trajeto t ON t.id = v.trajeto_id
             WHERE v.status <> 'cancelado' AND v.data_voo >= CURDATE()
             UNION
             SELECT t.destino AS codigo
             FROM voo v
             JOIN trajeto t ON t.id = v.trajeto_id
             WHERE v.status <> 'cancelado' AND v.data_voo >= CURDATE()
         ) AS aeroportos_disponiveis
         ORDER BY codigo"
    )->fetchAll(PDO::FETCH_COLUMN);

    // A gente mostra até 12 voos que atendem aos filtros.
    $comando = $conexao_pdo->query(
        "SELECT v.id, v.numero_voo, v.data_voo, v.status, v.valor_base, v.aeronave_id,
                t.origem, t.destino, t.h_partida, t.h_chegada,
                CONCAT(v.data_voo, ' ', t.h_partida) AS partida_em,
                CONCAT(v.data_voo, ' ', t.h_chegada) AS chegada_em,
                a.modelo, a.identificacao,
                (a.fileiras * a.assentos_por_fileira) AS capacidade,
                COALESCE((a.fileiras * a.assentos_por_fileira) - (
                    SELECT COUNT(*) FROM passagem 
                    WHERE voo_id = v.id AND status IN ('confirmada', 'checkin')
                ), a.fileiras * a.assentos_por_fileira) AS assentos_livres
         FROM voo v
         JOIN trajeto t ON t.id = v.trajeto_id
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
    // Se a consulta falhar, guardamos o aviso para mostrar na tela.
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
        <label class="rotulo-busca-voos">Origem</label>
        <input class="entrada-busca-voos" name="origin" list="codigos-aeroportos" placeholder="Escolha uma origem" maxlength="60" autocomplete="off">
      </div>
      
      <div>
        <label class="rotulo-busca-voos">Destino</label>
        <input class="entrada-busca-voos" name="destination" list="codigos-aeroportos" placeholder="Escolha um destino" maxlength="60" autocomplete="off">
      </div>
      
      <div>
        <label class="rotulo-busca-voos">Data do voo</label>
        <input class="entrada-busca-voos" type="date" name="date">
      </div>

      <datalist id="codigos-aeroportos">
        <?php foreach ($codigos_aeroportos as $codigo_aeroporto): ?>
          <option value="<?= esc($codigo_aeroporto) ?>"></option>
        <?php endforeach; ?>
      </datalist>

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

/**
 * Aqui a gente monta o cartão com os dados de um voo.
 * 
 * @param array $voo Dados do voo da view vw_voos_disponibilidade
 * @return string HTML do card formatado
 */
function card_voo_html(array $voo): string
{
    // A duração é calculada a partir dos horários de partida e chegada.
    $data_partida = new DateTime($voo['partida_em']);
    $data_chegada = new DateTime($voo['chegada_em']);
    $diferenca_tempo = $data_partida->diff($data_chegada);
    $duracao_formatada = $diferenca_tempo->h . 'h ' . str_pad((string) $diferenca_tempo->i, 2, '0', STR_PAD_LEFT) . 'm';
    
    // Também mostramos quantos assentos ainda estão livres.
    $assentos_livres = (int) $voo['assentos_livres'];
    $capacidade_total = (int) $voo['capacidade'];
    $poucas_assentos = $assentos_livres <= max(4, (int) ceil($capacidade_total * .2));
    
    ob_start();
    ?>
    <div class="flight-card-simples cartao-voo-simples" 
         data-flight-card 
         data-flight-id="<?= (int) $voo['id'] ?>" 
         data-origin="<?= esc($voo['origem']) ?>" 
         data-destination="<?= esc($voo['destino']) ?>" 
         data-date="<?= esc($voo['data_voo']) ?>">
      
      <div class="conteudo-voo-simples">
        <div class="linha-voo-simples">
          <strong><?= esc($voo['numero_voo']) ?></strong> - <?= esc($voo['modelo']) ?> (<?= esc($voo['identificacao']) ?>) - <?= $voo['status'] === 'embarque' ? 'Embarque' : 'Programado' ?>
        </div>
        
        <div class="linha-voo-simples">
          <?= $data_partida->format('H:i') ?> <?= esc($voo['origem']) ?> → <?= $data_chegada->format('H:i') ?> <?= esc($voo['destino']) ?> (<?= $duracao_formatada ?>)
        </div>
        
        <div class="linha-voo-simples">
          Preço: <?= formatar_moeda_brl($voo['valor_base']) ?> | Assentos: <?= $assentos_livres ?>/<?= $capacidade_total ?>
        </div>
        
        <div>
          <button class="select-flight-btn botao-escolher-assento" data-flight-id="<?= (int) $voo['id'] ?>">
            Escolher assento
          </button>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

// Aqui começa o modal usado para escolher um assento.
?>

<!-- A pessoa escolhe o assento neste modal. -->
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

<!-- O JavaScript usa este elemento para mostrar avisos rápidos. -->
<div id="toast" class="aviso-flutuante" hidden></div>


<?php
renderizar_rodape();
