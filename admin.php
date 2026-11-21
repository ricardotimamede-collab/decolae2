<?php

/**
 * Aqui a gente cuida da operação dos voos.
 * 
 * O administrador cadastra voos, monta as equipes e acompanha a operação.
 */

declare(strict_types=1);

// Aqui a gente carrega as funções e o layout usados nesta tela.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Primeiro, a gente confirma que o acesso está no perfil certo.

// A página usa esta conexão para consultar e atualizar os dados.
$conexao_pdo = db();

// Só quem está no perfil de administrador pode continuar.
$usuario_logado = exigir_papel($conexao_pdo, 'admin');

// Aqui ficam os avisos que aparecem depois das ações do formulário.
$feedback_admin = $_SESSION['feedback_admin'] ?? [];
unset($_SESSION['feedback_admin']);
$mensagem_sucesso = $feedback_admin['sucesso'] ?? null;
$mensagem_erro = $feedback_admin['erro'] ?? null;

// Essas funções ajudam a validar os dados enviados pelo formulário.

/**
 * Aqui a gente pega um campo obrigatório enviado pelo formulário.
 * 
 * @param string $nomeCampo Nome do campo
 * @return string Valor do campo trimado
 * @throws InvalidArgumentException Se campo vazio
 */
function obter_campo_obrigatorio_admin(string $nomeCampo): string
{
    $valor = trim((string) ($_POST[$nomeCampo] ?? ''));
    
    if ($valor === '') {
        throw new InvalidArgumentException('Preencha todos os campos.');
    }
    
    return $valor;
}

/**
 * Aqui a gente junta a data do voo aos horários do trajeto.
 * 
 * @param PDO $conexao Conexão com banco
 * @param int $id_voo ID do voo
 * @return array|null Dados do voo ou null
 */
function obter_horarios_voo(PDO $conexao, int $id_voo): ?array
{
    $comando = $conexao->prepare(
        'SELECT v.numero_voo,
                TIMESTAMP(v.data_voo, t.h_partida) AS partida_em,
                TIMESTAMP(v.data_voo, t.h_chegada) AS chegada_em
         FROM voo v
         JOIN trajeto t ON t.id = v.trajeto_id
         WHERE v.id = ?'
    );
    $comando->execute([$id_voo]);
    
    return $comando->fetch() ?: null;
}

/**
 * Aqui a gente confere se a aeronave está livre no horário solicitado.
 * 
 * @param PDO $conexao Conexão com banco
 * @param int $id_aeronave ID da aeronave
 * @param string $partida Timestamp de partida (ISO)
 * @param string $chegada Timestamp de chegada (ISO)
 * @return array|null Voo em conflito ou null
 */
function verificar_conflito_aeronave(
    PDO $conexao,
    int $id_aeronave,
    string $partida,
    string $chegada
): ?array {
    // Se outro voo ocupar esse horário, a gente devolve o conflito.
    $comando = $conexao->prepare(
        "SELECT v.numero_voo
         FROM voo v
         JOIN trajeto t ON t.id = v.trajeto_id
         WHERE v.aeronave_id = ?
         AND v.status <> 'cancelado'
         AND TIMESTAMP(v.data_voo, t.h_partida) < ?
         AND TIMESTAMP(v.data_voo, t.h_chegada) > ?
         LIMIT 1"
    );
    $comando->execute([$id_aeronave, $chegada, $partida]);
    
    return $comando->fetch() ?: null;
}

/**
 * Aqui a gente confere os horários e o descanso do tripulante.
 * 
 * Além de evitar sobreposição, a escala precisa respeitar 36 horas de descanso.
 * 
 * @param PDO $conexao Conexão com banco
 * @param string $cpf_tripulante CPF do tripulante
 * @param int $id_voo ID do voo (atual, para exclusão)
 * @param string $partida Timestamp de partida
 * @param string $chegada Timestamp de chegada
 * @return array|null Voo em conflito ou null
 */
function verificar_conflito_tripulante(
    PDO $conexao,
    string $cpf_tripulante,
    int $id_voo,
    string $partida,
    string $chegada
): ?array {
    // A consulta procura sobreposição e falta de descanso antes ou depois do voo.
    $sql = "
        SELECT v2.numero_voo
        FROM escala e
        JOIN voo v2 ON v2.id = e.voo_id
        JOIN trajeto t2 ON t2.id = v2.trajeto_id
        WHERE e.tripulante_cpf = ?
        AND e.voo_id <> ?
        AND v2.status <> 'cancelado'
        AND (
            -- Caso 1: Sobreposição de horários
            (TIMESTAMP(v2.data_voo, t2.h_partida) < ? 
             AND TIMESTAMP(v2.data_voo, t2.h_chegada) > ?)
            -- Caso 2: Descanso mínimo após outro voo
            OR (TIMESTAMP(v2.data_voo, t2.h_chegada) <= ? 
                AND TIMESTAMPDIFF(HOUR, TIMESTAMP(v2.data_voo, t2.h_chegada), ?) < 36)
            -- Caso 3: Descanso mínimo antes de outro voo
            OR (TIMESTAMP(v2.data_voo, t2.h_partida) >= ? 
                AND TIMESTAMPDIFF(HOUR, ?, TIMESTAMP(v2.data_voo, t2.h_partida)) < 36)
        )
        LIMIT 1
    ";
    
    $comando = $conexao->prepare($sql);
    $comando->execute([$cpf_tripulante, $id_voo, $chegada, $partida, $partida, $partida, $chegada, $chegada]);
    
    return $comando->fetch() ?: null;
}

// Aqui a gente valida e processa o que foi enviado pelos formulários.

if (eh_post()) {
    try {
        // Antes de continuar, a gente confere o token de segurança.
        verificar_token_csrf($_POST['token_csrf'] ?? null);
        
        // Esse campo indica qual formulário foi enviado.
        $acao = $_POST['acao'] ?? '';
        
        /**
         * Aqui a gente prepara o cadastro de um voo.
         * 
         * O formulário informa o número, o trajeto, a aeronave, a data e o preço.
         */
        if ($acao === 'voo') {
            // Primeiro a gente lê os dados enviados.
            $numero_voo = strtoupper(obter_campo_obrigatorio_admin('numero_voo'));
            $id_trajeto = (int) obter_campo_obrigatorio_admin('id_trajeto');
            $id_aeronave = (int) obter_campo_obrigatorio_admin('id_aeronave');
            $data_voo = normalizar_data(obter_campo_obrigatorio_admin('data_voo'));
            
            // A data precisa estar no formato esperado.
            if (!$data_voo) {
                throw new InvalidArgumentException('Data de voo inválida.');
            }

            $comando = $conexao_pdo->prepare(
                'SELECT 1 FROM voo WHERE numero_voo = ? AND data_voo = ? LIMIT 1'
            );
            $comando->execute([$numero_voo, $data_voo]);
            if ($comando->fetchColumn()) {
                throw new InvalidArgumentException(
                    "Já existe um voo {$numero_voo} cadastrado nessa data."
                );
            }
            
            // Os horários de partida e chegada vêm do trajeto escolhido.
            $comando_trajeto = $conexao_pdo->prepare(
                'SELECT h_partida, h_chegada FROM trajeto WHERE id = ?'
            );
            $comando_trajeto->execute([$id_trajeto]);
            $dados_trajeto = $comando_trajeto->fetch();
            
            // Sem um trajeto válido, não dá para criar o voo.
            if (!$dados_trajeto) {
                throw new InvalidArgumentException('Trajeto não encontrado.');
            }
            
            // A gente junta a data do voo aos horários da rota.
            $timestamp_partida = $data_voo . ' ' . $dados_trajeto['h_partida'];
            $timestamp_chegada = $data_voo . ' ' . $dados_trajeto['h_chegada'];
            
            // O horário de chegada precisa ser posterior ao de partida.
            if (strtotime($timestamp_chegada) <= strtotime($timestamp_partida)) {
                throw new InvalidArgumentException(
                    'O horário de chegada do trajeto deve ser posterior à partida.'
                );
            }
            
            // A transação evita deixar um cadastro pela metade.
            $conexao_pdo->beginTransaction();
            
            try {
                // Antes de salvar, a gente confere se a aeronave está livre.
                $conflito_aeronave = verificar_conflito_aeronave(
                    $conexao_pdo,
                    $id_aeronave,
                    $timestamp_chegada,
                    $timestamp_partida
                );
                
                if ($conflito_aeronave) {
                    throw new InvalidArgumentException(
                        "A aeronave já está alocada ao voo {$conflito_aeronave['numero_voo']} nesse intervalo."
                    );
                }
                
                // Aqui a gente prepara o novo registro do voo.
                $comando = $conexao_pdo->prepare(
                    "INSERT INTO voo (numero_voo, data_voo, trajeto_id, aeronave_id, admin_cpf, valor_base, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'programado')"
                );
                
                // Agora a gente grava o voo no banco.
                $comando->execute([
                    $numero_voo,
                    $data_voo,
                    $id_trajeto,
                    $id_aeronave,
                    $usuario_logado['cpf'],
                    (float) ($_POST['valor_base'] ?? 649.90)
                ]);
                
                // Também deixamos registrado quem criou o voo.
                $id_voo_criado = $conexao_pdo->lastInsertId();
                registrar_acao(
                    $conexao_pdo,
                    'VOO_CRIADO',
                    "Voo {$numero_voo} de {$data_voo} criado usando os horários do trajeto.",
                    'voo:' . $id_voo_criado,
                    $usuario_logado['cpf']
                );
                
                // Tudo deu certo, então a gente confirma as alterações.
                $conexao_pdo->commit();
                $mensagem_sucesso = 'Voo criado. Agora aloque a equipe mínima.';
            } catch (Throwable $excecao) {
                // Se algo falhar, a gente desfaz o que foi feito na transação.
                $conexao_pdo->rollBack();
                throw $excecao;
            }
        }
        /**
         * Aqui a gente inclui um tripulante na equipe do voo.
         * 
         * O formulário informa o voo, a pessoa e a função na equipe.
         */
        elseif ($acao === 'escala') {
            // Primeiro a gente lê os dados da escala.
            $id_voo = (int) obter_campo_obrigatorio_admin('id_voo');
            $cpf_tripulante = obter_campo_obrigatorio_admin('cpf_tripulante');
            $funcao_tripulante = obter_campo_obrigatorio_admin('funcao_tripulante');
            
            // A função precisa estar entre as opções aceitas.
            $funcoes_validas = ['piloto', 'copiloto', 'comissario'];
            if (!in_array($funcao_tripulante, $funcoes_validas, true)) {
                throw new InvalidArgumentException('Função inválida.');
            }
            
            // A gente confirma que o voo escolhido existe.
            $dados_voo = obter_horarios_voo($conexao_pdo, $id_voo);
            if (!$dados_voo) {
                throw new InvalidArgumentException('Voo não encontrado.');
            }
            
            // A função escolhida precisa ser compatível com o cadastro da pessoa.
            $comando = $conexao_pdo->prepare(
                'SELECT funcao FROM tripulante WHERE cpf = ?'
            );
            $comando->execute([$cpf_tripulante]);
            $funcao_cadastrada = $comando->fetchColumn();
            $funcao_compativel = $funcao_cadastrada === $funcao_tripulante
                || (
                    $funcao_tripulante === 'copiloto'
                    && $funcao_cadastrada === 'piloto'
                );
            if (!$funcao_compativel) {
                throw new InvalidArgumentException('O tripulante não está cadastrado para essa função.');
            }
            
            // Antes de escalar, a gente confere a jornada e o descanso.
            $conflito_jornada = verificar_conflito_tripulante(
                $conexao_pdo,
                $cpf_tripulante,
                $id_voo,
                $dados_voo['partida_em'],
                $dados_voo['chegada_em']
            );
            
            if ($conflito_jornada) {
                throw new InvalidArgumentException(
                    "Conflito de escala com {$conflito_jornada['numero_voo']} ou descanso inferior a 36 horas."
                );
            }
            
            // Aqui a gente prepara a inclusão na equipe.
            $comando = $conexao_pdo->prepare(
                'INSERT INTO escala (voo_id, tripulante_cpf, funcao_tripulante)
                 VALUES (?, ?, ?)'
            );
            $comando->execute([$id_voo, $cpf_tripulante, $funcao_tripulante]);
            
            // Também guardamos no log quem fez a alocação.
            registrar_acao(
                $conexao_pdo,
                'ESCALA_CRIADA',
                "Tripulante {$cpf_tripulante} alocado ao voo {$dados_voo['numero_voo']} como {$funcao_tripulante}.",
                'voo:' . $id_voo,
                $usuario_logado['cpf']
            );
            
            $mensagem_sucesso = 'Escala salva sem conflito de jornada.';
        }
    } catch (Throwable $excecao) {
        // Se a operação falhar, a gente desfaz qualquer transação aberta.
        if ($conexao_pdo->inTransaction()) {
            $conexao_pdo->rollBack();
        }
        
        // O aviso vai aparecer para quem enviou o formulário.
        $mensagem_erro = $excecao->getMessage();
        if (
            $excecao instanceof PDOException
            && (int)($excecao->errorInfo[1] ?? 0) === 1062
            && strpos((string)($excecao->errorInfo[2] ?? ''), 'uq_voo_numero_data') !== false
        ) {
            $mensagem_erro = 'Já existe um voo com esse número nessa data.';
        }
    }
}

if (eh_post()) {
    $_SESSION['feedback_admin'] = [
        'sucesso' => $mensagem_sucesso,
        'erro' => $mensagem_erro
    ];
    header('Location: admin.php', true, 303);
    exit;
}

// Agora a gente busca os dados que aparecem nos formulários e nas listas.

/**
 * Aqui ficam os trajetos que podem ser usados em novos voos.
 */
$trajetos = $conexao_pdo->query(
    'SELECT id, origem, destino, h_partida, h_chegada
     FROM trajeto
     ORDER BY origem, destino'
)->fetchAll();

/**
 * Aqui a gente lista as aeronaves e a quantidade de assentos de cada uma.
 */
$aeronaves = $conexao_pdo->query(
    'SELECT a.id, a.identificacao, a.modelo, COUNT(s.id) AS capacidade
     FROM aeronave a
     LEFT JOIN assento s ON s.aeronave_id = a.id
     GROUP BY a.id
     ORDER BY a.identificacao'
)->fetchAll();

/**
 * Aqui a gente carrega a equipe que pode ser escalada.
 */
$tripulantes = $conexao_pdo->query(
    'SELECT u.cpf, u.nome_completo, t.funcao
     FROM tripulante t
     JOIN usuario u ON u.cpf = t.cpf
     ORDER BY u.nome_completo'
)->fetchAll();

/**
 * A lista mostra cada voo e quantas pessoas já estão em cada função.
 */
$voos = $conexao_pdo->query(
    "SELECT v.id, v.numero_voo, v.data_voo,
            TIMESTAMP(v.data_voo, t.h_partida) AS partida_em,
            t.h_partida, t.origem, t.destino,
            a.identificacao, a.modelo,
            SUM(e.funcao_tripulante = 'piloto') AS pilotos,
            SUM(e.funcao_tripulante = 'copiloto') AS copilotos,
            SUM(e.funcao_tripulante = 'comissario') AS comissarios
     FROM voo v
     JOIN trajeto t ON t.id = v.trajeto_id
     JOIN aeronave a ON a.id = v.aeronave_id
     LEFT JOIN escala e ON e.voo_id = v.id
     GROUP BY v.id, v.numero_voo, v.data_voo, t.h_partida, t.origem, t.destino, a.identificacao, a.modelo
     ORDER BY v.data_voo, t.h_partida"
)->fetchAll();

/**
 * Aqui ficam as ações mais recentes feitas no sistema.
 */
$logs_recentes = $conexao_pdo->query(
    'SELECT l.*, u.nome_completo
     FROM log l
     JOIN usuario u ON u.cpf = l.usuario_cpf
     ORDER BY l.data_hora DESC
     LIMIT 8'
)->fetchAll();

// A partir daqui a gente monta a tela do administrador.
renderizar_cabecalho('Administração de voos', $usuario_logado);
?>

<div class="pagina-administracao">
  <h1 class="titulo-pagina">Operação de Voos</h1>
  
  <?php if ($mensagem_sucesso): ?>
    <div class="aviso aviso-sucesso">
      ✓ <?= esc($mensagem_sucesso) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($mensagem_erro): ?>
    <div class="aviso aviso-erro">
      ✗ <?= esc($mensagem_erro) ?>
    </div>
  <?php endif; ?>
  
  <!-- Aqui ficam os formulários de operação. -->
  <div class="grade-formularios grade-formularios-administracao">
    
    <!-- Neste formulário a gente cadastra um novo voo. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Criar Voo</h2>
      <form method="post">
        <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
        <input type="hidden" name="acao" value="voo">
        
        <div class="campo-form">
          <label>Número do Voo</label>
          <input name="numero_voo" placeholder="Ex: DC305" required>
        </div>
        
        <div class="campo-form">
          <label>Trajeto</label>
          <select name="id_trajeto" required>
            <option value="">Selecione...</option>
            <?php foreach ($trajetos as $trajeto): ?>
              <option value="<?= $trajeto['id'] ?>">
                <?= esc($trajeto['origem']) ?> → <?= esc($trajeto['destino']) ?>
                (<?= esc(substr($trajeto['h_partida'], 0, 5)) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="campo-form">
          <label>Aeronave</label>
          <select name="id_aeronave" required>
            <option value="">Selecione...</option>
            <?php foreach ($aeronaves as $aeronave): ?>
              <option value="<?= $aeronave['id'] ?>">
                <?= esc($aeronave['identificacao']) ?> (<?= $aeronave['capacidade'] ?> assentos)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="campo-form">
          <label>Data do Voo</label>
          <input name="data_voo" type="date" value="<?= esc(date('Y-m-d', strtotime('+3 days'))) ?>" required>
        </div>
        
        <div class="campo-form">
          <label>Valor Base (R$)</label>
          <input name="valor_base" type="number" min="0" step="0.01" value="649.90" required>
        </div>
        
        <button type="submit" class="botao botao-bloco">Criar Voo</button>
      </form>
    </div>
    
    <!-- Neste formulário a gente monta a equipe de um voo. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Alocar Tripulante</h2>
      <form method="post">
        <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
        <input type="hidden" name="acao" value="escala">
        
        <div class="campo-form">
          <label>Voo</label>
          <select name="id_voo" required>
            <option value="">Selecione...</option>
            <?php foreach ($voos as $voo): ?>
              <option value="<?= $voo['id'] ?>">
                <?= esc($voo['numero_voo']) ?> · <?= esc(date('d/m/Y', strtotime($voo['data_voo']))) ?> · <?= esc($voo['origem']) ?> → <?= esc($voo['destino']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="campo-form">
          <label>Função</label>
          <select id="funcao-tripulante" name="funcao_tripulante" required>
            <option value="">Selecione...</option>
            <option value="piloto">Piloto</option>
            <option value="copiloto">Copiloto</option>
            <option value="comissario">Comissário de Bordo</option>
          </select>
          <small>Pilotos também podem ser alocados como copilotos.</small>
        </div>

        <div class="campo-form">
          <label>Tripulante</label>
          <select id="cpf-tripulante" name="cpf_tripulante" required disabled>
            <option value="">Selecione primeiro a função...</option>
            <?php foreach ($tripulantes as $tripulante): ?>
              <option value="<?= esc($tripulante['cpf']) ?>" data-funcao="<?= esc($tripulante['funcao'] ?? '') ?>">
                <?= esc($tripulante['nome_completo']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small id="tripulantes-disponiveis" aria-live="polite">Selecione uma função para carregar os tripulantes.</small>
        </div>
        
        <button type="submit" class="botao botao-bloco">Alocar Tripulante</button>
      </form>
    </div>
  </div>
  
  <!-- Aqui a gente acompanha o status e a equipe de cada voo. -->
  <div class="cartao-formulario cartao-operacional">
    <h2 class="titulo-formulario">Status Operacional dos Voos</h2>
    <p class="texto-ajuda">Requerido: 1 piloto · 1 copiloto · 2 comissários</p>
    
    <?php if(count($voos) > 0): ?>
      <table class="tabela-operacional">
        <thead>
          <tr>
            <th>Voo</th>
            <th>Rota</th>
            <th>Partida</th>
            <th>Equipe</th>
            <th>Situação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos as $voo): ?>
            <?php
              $pilotos = (int)$voo['pilotos'];
              $copilotos = (int)$voo['copilotos'];
              $comissarios = (int)$voo['comissarios'];
              $completo = $pilotos >= 1 && $copilotos >= 1 && $comissarios >= 2;
            ?>
            <tr>
              <td><strong><?= esc($voo['numero_voo']) ?></strong></td>
              <td>
                <?= esc($voo['origem']) ?> → <?= esc($voo['destino']) ?>
                <br><span class="texto-secundario texto-secundario-pequeno">
                  <?= esc($voo['modelo']) ?>
                </span>
              </td>
              <td class="texto-secundario">
                <?= esc(formatar_data_hora_br($voo['partida_em'])) ?>
              </td>
              <td class="texto-secundario">
                P: <?= $pilotos ?> | C: <?= $copilotos ?> | CB: <?= $comissarios ?>
              </td>
              <td>
                <span class="status-equipe <?= $completo ? 'status-equipe-liberado' : 'status-equipe-pendente' ?>">
                  <?= $completo ? '✓ Liberado' : '⊘ Aguardando' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="texto-vazio">Nenhum voo cadastrado.</p>
    <?php endif; ?>
  </div>
  
  <!-- Aqui aparecem as ações recentes para consulta. -->
  <div class="cartao-formulario">
    <h2 class="titulo-formulario">Últimos Logs</h2>
    <?php if(count($logs_recentes) > 0): ?>
      <div class="grade-logs">
        <?php foreach ($logs_recentes as $log): ?>
          <div class="cartao-log">
            <div class="cartao-log-cabecalho">
              <strong><?= esc($log['acao']) ?></strong>
              <span class="texto-vazio">
                <?= esc(formatar_data_hora_br($log['data_hora'])) ?>
              </span>
            </div>
            <p class="texto-secundario texto-sem-margem">
              <?= esc($log['descricao']) ?>
            </p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="texto-vazio">Nenhum log registrado.</p>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
    const selecaoFuncao = document.querySelector('#funcao-tripulante');
    const selecaoTripulante = document.querySelector('#cpf-tripulante');
    const avisoTripulantes = document.querySelector('#tripulantes-disponiveis');

    if (!selecaoFuncao || !selecaoTripulante || !avisoTripulantes) {
        return;
    }

    const tripulantes = Array.from(selecaoTripulante.querySelectorAll('option[data-funcao]'))
        .map(opcao => ({
            cpf: opcao.value,
            nome: opcao.textContent.trim(),
            funcao: opcao.dataset.funcao
        }));

    function atualizarTripulantes() {
        const funcao = selecaoFuncao.value;
        const correspondentes = tripulantes.filter(tripulante =>
            tripulante.funcao === funcao
            || (funcao === 'copiloto' && tripulante.funcao === 'piloto')
        );

        selecaoTripulante.innerHTML = '';
        selecaoTripulante.add(new Option('Selecione...', ''));

        if (!funcao) {
            selecaoTripulante.disabled = true;
            avisoTripulantes.textContent = 'Selecione uma função para carregar os tripulantes.';
            return;
        }

        correspondentes.forEach(tripulante => {
            selecaoTripulante.add(new Option(tripulante.nome, tripulante.cpf));
        });

        selecaoTripulante.disabled = false;
        avisoTripulantes.textContent = correspondentes.length
            ? `${correspondentes.length} tripulante(s) disponível(is) para essa função.`
            : 'Nenhum tripulante cadastrado para essa função.';
    }

    selecaoFuncao.addEventListener('change', atualizarTripulantes);
    atualizarTripulantes();
})();
</script>

<?php renderizar_rodape(); ?>
