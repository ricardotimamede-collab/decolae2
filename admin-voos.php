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
require_once __DIR__ . '/includes/gerencia.php';

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
$aviso_na_lista = !empty($feedback_admin['na_lista']);

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
                TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY AS chegada_em
         FROM voo v
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
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
    string $chegada,
    int $ignorar_voo = 0
): ?array {
    // Se outro voo ocupar esse horário, a gente devolve o conflito.
    $comando = $conexao->prepare(
        "SELECT v.numero_voo
         FROM voo v
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
         WHERE v.aeronave_id = ?
         AND v.id <> ?
         AND v.status <> 'cancelado'
         AND TIMESTAMP(v.data_voo, t.h_partida) < ?
         AND TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY > ?
         LIMIT 1"
    );
    $comando->execute([$id_aeronave, $ignorar_voo, $chegada, $partida]);

    return $comando->fetch() ?: null;
}

function verificar_localizacao_aeronave(
    PDO $conexao,
    int $id_aeronave,
    string $origem,
    string $destino,
    string $partida,
    string $chegada,
    int $ignorar_voo = 0
): ?string {
    $mesmo_local = static fn(string $a, string $b): bool => mb_strtolower(trim($a)) === mb_strtolower(trim($b));

    $comando = $conexao->prepare(
        "SELECT v.numero_voo, t.destino
         FROM voo v
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
         WHERE v.aeronave_id = ?
         AND v.id <> ?
         AND v.status <> 'cancelado'
         AND TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY <= ?
         ORDER BY TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY DESC
         LIMIT 1"
    );
    $comando->execute([$id_aeronave, $ignorar_voo, $partida]);
    $anterior = $comando->fetch();

    if ($anterior && !$mesmo_local($anterior['destino'], $origem)) {
        return "Esta aeronave estará em {$anterior['destino']} após o voo {$anterior['numero_voo']}, "
            . "e este voo sai de {$origem}.";
    }

    $comando = $conexao->prepare(
        "SELECT v.numero_voo, t.origem
         FROM voo v
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
         WHERE v.aeronave_id = ?
         AND v.id <> ?
         AND v.status <> 'cancelado'
         AND TIMESTAMP(v.data_voo, t.h_partida) >= ?
         ORDER BY TIMESTAMP(v.data_voo, t.h_partida) ASC
         LIMIT 1"
    );
    $comando->execute([$id_aeronave, $ignorar_voo, $chegada]);
    $seguinte = $comando->fetch();

    if ($seguinte && !$mesmo_local($seguinte['origem'], $destino)) {
        return "Este voo termina em {$destino}, mas o voo seguinte desta aeronave "
            . "({$seguinte['numero_voo']}) sai de {$seguinte['origem']}.";
    }

    return null;
}

/**
 * 
 * 
 * @param PDO $conexao Conexão com banco
 * @param string $cpf_tripulante CPF do tripulante
 * @param int $id_voo ID do voo (atual, para exclusão)
 * @param string $partida Timestamp de partida
 * @param string $chegada Timestamp de chegada
 */
function verificar_conflito_tripulante(
    PDO $conexao,
    string $cpf_tripulante,
    int $id_voo,
    string $partida,
    string $chegada
): ?string {
    $comando = $conexao->prepare(
        "SELECT v2.numero_voo,
                TIMESTAMP(v2.data_voo, t2.h_partida) AS partida_em,
                TIMESTAMP(v2.data_voo, t2.h_chegada) + INTERVAL (t2.h_chegada < t2.h_partida) DAY AS chegada_em
         FROM escala e
         JOIN voo v2 ON v2.id = e.voo_id
         JOIN trajeto t2 ON t2.origem = v2.origem AND t2.destino = v2.destino AND t2.h_partida = v2.h_partida
         WHERE e.tripulante_cpf = ?
         AND e.voo_id <> ?
         AND v2.status <> 'cancelado'"
    );
    $comando->execute([$cpf_tripulante, $id_voo]);
    
    return avaliar_escala_tripulante($comando->fetchAll(), $partida, $chegada);
}

function contar_passagens(PDO $conexao, int $id_voo): int
{
    $comando = $conexao->prepare('SELECT COUNT(*) FROM passagem WHERE voo_id = ?');
    $comando->execute([$id_voo]);

    return (int) $comando->fetchColumn();
}

function descrever_passagens(int $total): string
{
    return $total === 1 ? '1 passagem' : $total . ' passagens';
}

function montar_horarios_voo(array $trajeto, string $data_voo): array
{
    $partida = $data_voo . ' ' . $trajeto['h_partida'];
    $chegada = $data_voo . ' ' . $trajeto['h_chegada'];

    if ($trajeto['h_chegada'] < $trajeto['h_partida']) {
        $chegada = date('Y-m-d H:i:s', strtotime($chegada . ' +1 day'));
    }

    if (strtotime($chegada) <= strtotime($partida)) {
        throw new InvalidArgumentException('O horário de chegada do trajeto não pode ser igual ao de partida.');
    }

    return [$partida, $chegada];
}

function funcao_serve_ao_papel(?string $cadastrada, string $papel): bool
{
    return $cadastrada === $papel || ($papel === 'copiloto' && $cadastrada === 'piloto');
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
            $codigo_trajeto = obter_campo_obrigatorio_admin('id_trajeto');
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
                'SELECT origem, destino, h_partida, h_chegada FROM trajeto
                 WHERE origem = ? AND destino = ? AND h_partida = ?'
            );
            $comando_trajeto->execute(decodificar_trajeto($codigo_trajeto));
            $dados_trajeto = $comando_trajeto->fetch();
            
            // Sem um trajeto válido, não dá para criar o voo.
            if (!$dados_trajeto) {
                throw new InvalidArgumentException('Trajeto não encontrado.');
            }
            
            // A gente junta a data do voo aos horários da rota.
            $timestamp_partida = $data_voo . ' ' . $dados_trajeto['h_partida'];
            $timestamp_chegada = $data_voo . ' ' . $dados_trajeto['h_chegada'];
            
            if ($dados_trajeto['h_chegada'] < $dados_trajeto['h_partida']) {
                $timestamp_chegada = date('Y-m-d H:i:s', strtotime($timestamp_chegada . ' +1 day'));
            }
            
            if (strtotime($timestamp_chegada) <= strtotime($timestamp_partida)) {
                throw new InvalidArgumentException(
                    'O horário de chegada do trajeto não pode ser igual ao de partida.'
                );
            }
            
            // A transação evita deixar um cadastro pela metade.
            $conexao_pdo->beginTransaction();
            
            try {
                // Antes de salvar, a gente confere se a aeronave está livre.
                $conflito_aeronave = verificar_conflito_aeronave(
                    $conexao_pdo,
                    $id_aeronave,
                    $timestamp_partida,
                    $timestamp_chegada
                );
                
                if ($conflito_aeronave) {
                    throw new InvalidArgumentException(
                        "A aeronave já está alocada ao voo {$conflito_aeronave['numero_voo']} nesse intervalo."
                    );
                }

                $erro_localizacao = verificar_localizacao_aeronave(
                    $conexao_pdo,
                    $id_aeronave,
                    $dados_trajeto['origem'],
                    $dados_trajeto['destino'],
                    $timestamp_partida,
                    $timestamp_chegada
                );

                if ($erro_localizacao) {
                    throw new InvalidArgumentException($erro_localizacao);
                }
                
                // Aqui a gente prepara o novo registro do voo.
                $comando = $conexao_pdo->prepare(
                    "INSERT INTO voo (numero_voo, data_voo, origem, destino, h_partida, aeronave_id, admin_cpf, valor_base, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'programado')"
                );
                
                // Agora a gente grava o voo no banco.
                $comando->execute([
                    $numero_voo,
                    $data_voo,
                    $dados_trajeto['origem'],
                    $dados_trajeto['destino'],
                    $dados_trajeto['h_partida'],
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
            $motivo_recusa = verificar_conflito_tripulante(
                $conexao_pdo,
                $cpf_tripulante,
                $id_voo,
                $dados_voo['partida_em'],
                $dados_voo['chegada_em']
            );
            
            if ($motivo_recusa) {
                throw new InvalidArgumentException($motivo_recusa);
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
        elseif ($acao === 'voo_editar') {
            $id_voo = (int) obter_campo_obrigatorio_admin('id');
            $numero_voo = strtoupper(obter_campo_obrigatorio_admin('numero_voo'));
            $codigo_trajeto = obter_campo_obrigatorio_admin('id_trajeto');
            $id_aeronave = (int) obter_campo_obrigatorio_admin('id_aeronave');
            $data_voo = normalizar_data(obter_campo_obrigatorio_admin('data_voo'));
            $valor_base = (float) obter_campo_obrigatorio_admin('valor_base');

            if (!$data_voo) {
                throw new InvalidArgumentException('Data de voo inválida.');
            }

            $comando = $conexao_pdo->prepare('SELECT 1 FROM voo WHERE id = ?');
            $comando->execute([$id_voo]);
            if (!$comando->fetchColumn()) {
                throw new InvalidArgumentException('Voo não encontrado.');
            }

            $passagens = contar_passagens($conexao_pdo, $id_voo);
            if ($passagens > 0) {
                throw new InvalidArgumentException(
                    'Este voo não pode ser alterado porque tem ' . descrever_passagens($passagens) . '.'
                );
            }

            $comando = $conexao_pdo->prepare('SELECT 1 FROM voo WHERE numero_voo = ? AND data_voo = ? AND id <> ? LIMIT 1');
            $comando->execute([$numero_voo, $data_voo, $id_voo]);
            if ($comando->fetchColumn()) {
                throw new InvalidArgumentException("Já existe um voo {$numero_voo} cadastrado nessa data.");
            }

            $comando = $conexao_pdo->prepare(
                'SELECT origem, destino, h_partida, h_chegada FROM trajeto WHERE origem = ? AND destino = ? AND h_partida = ?'
            );
            $comando->execute(decodificar_trajeto($codigo_trajeto));
            $dados_trajeto = $comando->fetch();
            if (!$dados_trajeto) {
                throw new InvalidArgumentException('Trajeto não encontrado.');
            }

            [$timestamp_partida, $timestamp_chegada] = montar_horarios_voo($dados_trajeto, $data_voo);

            $equipe = [];
            $escolhidos = [];
            foreach (['piloto' => 'piloto', 'copiloto' => 'copiloto', 'comissario1' => 'comissario', 'comissario2' => 'comissario'] as $campo => $papel) {
                $cpf = trim((string) ($_POST[$campo] ?? ''));
                if ($cpf === '') {
                    continue;
                }

                if (isset($escolhidos[$cpf])) {
                    throw new InvalidArgumentException('A mesma pessoa não pode ocupar duas vagas do voo.');
                }

                $comando = $conexao_pdo->prepare(
                    'SELECT u.nome_completo, t.funcao FROM tripulante t JOIN usuario u ON u.cpf = t.cpf WHERE t.cpf = ?'
                );
                $comando->execute([$cpf]);
                $pessoa = $comando->fetch();

                if (!$pessoa || !funcao_serve_ao_papel($pessoa['funcao'], $papel)) {
                    throw new InvalidArgumentException('Um dos tripulantes escolhidos não está cadastrado para a função de ' . $papel . '.');
                }

                $escolhidos[$cpf] = true;
                $equipe[] = [$cpf, $papel, $pessoa['nome_completo']];
            }

            $conexao_pdo->beginTransaction();

            $conflito_aeronave = verificar_conflito_aeronave(
                $conexao_pdo, $id_aeronave, $timestamp_partida, $timestamp_chegada, $id_voo
            );
            if ($conflito_aeronave) {
                throw new InvalidArgumentException(
                    "A aeronave já está alocada ao voo {$conflito_aeronave['numero_voo']} nesse intervalo."
                );
            }

            $erro_localizacao = verificar_localizacao_aeronave(
                $conexao_pdo, $id_aeronave, $dados_trajeto['origem'], $dados_trajeto['destino'],
                $timestamp_partida, $timestamp_chegada, $id_voo
            );
            if ($erro_localizacao) {
                throw new InvalidArgumentException($erro_localizacao);
            }

            foreach ($equipe as [$cpf, $papel, $nome]) {
                $motivo_recusa = verificar_conflito_tripulante(
                    $conexao_pdo, $cpf, $id_voo, $timestamp_partida, $timestamp_chegada
                );
                if ($motivo_recusa) {
                    throw new InvalidArgumentException($nome . ': ' . $motivo_recusa);
                }
            }

            $comando = $conexao_pdo->prepare(
                'UPDATE voo SET numero_voo = ?, data_voo = ?, origem = ?, destino = ?, h_partida = ?, aeronave_id = ?, valor_base = ? WHERE id = ?'
            );
            $comando->execute([$numero_voo, $data_voo, $dados_trajeto['origem'], $dados_trajeto['destino'], $dados_trajeto['h_partida'], $id_aeronave, $valor_base, $id_voo]);

            $comando = $conexao_pdo->prepare('DELETE FROM escala WHERE voo_id = ?');
            $comando->execute([$id_voo]);

            $comando = $conexao_pdo->prepare('INSERT INTO escala (voo_id, tripulante_cpf, funcao_tripulante) VALUES (?, ?, ?)');
            foreach ($equipe as [$cpf, $papel, $nome]) {
                $comando->execute([$id_voo, $cpf, $papel]);
            }

            registrar_acao(
                $conexao_pdo,
                'VOO_ALTERADO',
                "Voo {$numero_voo} de {$data_voo} alterado.",
                'voo:' . $id_voo,
                $usuario_logado['cpf']
            );

            $conexao_pdo->commit();
            $mensagem_sucesso = 'Voo alterado.';
        }
        elseif ($acao === 'voo_excluir') {
            $id_voo = (int) obter_campo_obrigatorio_admin('id');

            $comando = $conexao_pdo->prepare('SELECT numero_voo, data_voo FROM voo WHERE id = ?');
            $comando->execute([$id_voo]);
            $voo_excluido = $comando->fetch();
            if (!$voo_excluido) {
                throw new InvalidArgumentException('Voo não encontrado.');
            }

            $passagens = contar_passagens($conexao_pdo, $id_voo);
            if ($passagens > 0) {
                throw new InvalidArgumentException(
                    'Este voo não pode ser excluído porque tem ' . descrever_passagens($passagens) . '.'
                );
            }

            $comando = $conexao_pdo->prepare('DELETE FROM voo WHERE id = ?');
            $comando->execute([$id_voo]);

            registrar_acao(
                $conexao_pdo,
                'VOO_EXCLUIDO',
                "Voo {$voo_excluido['numero_voo']} de {$voo_excluido['data_voo']} excluído.",
                'voo:' . $id_voo,
                $usuario_logado['cpf']
            );

            $mensagem_sucesso = 'Voo excluído.';
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

if (eh_post() && !(($_POST['acao'] ?? '') === 'voo_editar' && $mensagem_erro)) {
    $_SESSION['feedback_admin'] = [
        'sucesso' => $mensagem_sucesso,
        'erro' => $mensagem_erro,
        'na_lista' => ($_POST['acao'] ?? '') === 'voo_excluir'
    ];
    $destino = ($_POST['acao'] ?? '') === 'voo_excluir' ? 'admin-voos.php#lista-voos' : 'admin-voos.php';
    header('Location: ' . $destino, true, 303);
    exit;
}

// Agora a gente busca os dados que aparecem nos formulários e nas listas.

/**
 * Aqui ficam os trajetos que podem ser usados em novos voos.
 */
$trajetos = $conexao_pdo->query(
    'SELECT origem, destino, h_partida, h_chegada
     FROM trajeto
     ORDER BY origem, destino, h_partida'
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
            SUM(e.funcao_tripulante = 'comissario') AS comissarios,
            (SELECT COUNT(*) FROM passagem p WHERE p.voo_id = v.id) AS passagens
     FROM voo v
     JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
     JOIN aeronave a ON a.id = v.aeronave_id
     LEFT JOIN escala e ON e.voo_id = v.id
     GROUP BY v.id, v.numero_voo, v.data_voo, t.h_partida, t.origem, t.destino, a.identificacao, a.modelo
     ORDER BY v.data_voo, t.h_partida"
)->fetchAll();

$busca = trim((string) ($_GET['busca'] ?? ''));
$voos_listados = $busca === '' ? $voos : array_filter($voos, static function (array $voo) use ($busca): bool {
    $campos = [$voo['numero_voo'], $voo['origem'], $voo['destino'], $voo['identificacao'], $voo['modelo'], date('d/m/Y', strtotime($voo['data_voo']))];
    foreach ($campos as $campo) {
        if (mb_stripos((string) $campo, $busca) !== false) {
            return true;
        }
    }

    return false;
});

$voo_em_edicao = null;
$equipe_em_edicao = ['piloto' => '', 'copiloto' => '', 'comissario1' => '', 'comissario2' => ''];
$id_edicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($id_edicao) {
    $comando = $conexao_pdo->prepare('SELECT id, numero_voo, data_voo, origem, destino, h_partida, aeronave_id, valor_base FROM voo WHERE id = ?');
    $comando->execute([$id_edicao]);
    $voo_em_edicao = $comando->fetch() ?: null;

    if ($voo_em_edicao) {
        $passagens_em_uso = contar_passagens($conexao_pdo, $id_edicao);
        if ($passagens_em_uso > 0) {
            $mensagem_erro = 'Este voo não pode ser alterado porque tem ' . descrever_passagens($passagens_em_uso) . '.';
            $voo_em_edicao = null;
        }
    }

    if ($voo_em_edicao) {
        $comando = $conexao_pdo->prepare('SELECT tripulante_cpf, funcao_tripulante FROM escala WHERE voo_id = ? ORDER BY id');
        $comando->execute([$id_edicao]);
        foreach ($comando->fetchAll() as $escala_atual) {
            $campo = $escala_atual['funcao_tripulante'];
            if ($campo === 'comissario') {
                $campo = $equipe_em_edicao['comissario1'] === '' ? 'comissario1' : 'comissario2';
            }
            $equipe_em_edicao[$campo] = $escala_atual['tripulante_cpf'];
        }
    }
}

$valor_voo = fn(string $campo): string => esc($_POST[$campo] ?? (string) ($voo_em_edicao[$campo] ?? ''));
$trajeto_escolhido = (string) ($_POST['id_trajeto'] ?? (
    $voo_em_edicao ? codificar_trajeto($voo_em_edicao['origem'], $voo_em_edicao['destino'], $voo_em_edicao['h_partida']) : ''
));
$valor_equipe = fn(string $campo): string => (string) ($_POST[$campo] ?? $equipe_em_edicao[$campo]);
$pilotos_lista = array_filter($tripulantes, static fn(array $t): bool => $t['funcao'] === 'piloto');
$copilotos_lista = array_filter($tripulantes, static fn(array $t): bool => in_array($t['funcao'], ['piloto', 'copiloto'], true));
$comissarios_lista = array_filter($tripulantes, static fn(array $t): bool => $t['funcao'] === 'comissario');

// A partir daqui a gente monta a tela do administrador.
renderizar_cabecalho('Administração de voos', $usuario_logado);
?>

<div class="pagina-administracao">
  <a class="botao-pequeno" href="admin.php" style="margin-bottom: 20px;">← Voltar</a>
  <h1 class="titulo-pagina">Operação de Voos</h1>
  
  <?php if ($mensagem_sucesso && !$aviso_na_lista): ?>
    <div class="aviso aviso-sucesso">
      <?= esc($mensagem_sucesso) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($mensagem_erro && !$aviso_na_lista): ?>
    <div class="aviso aviso-erro">
      <?= esc($mensagem_erro) ?>
    </div>
  <?php endif; ?>
  
  
  <!-- Aqui ficam os formulários de operação. -->
  <div class="grade-formularios grade-formularios-administracao">
    
<?php if ($voo_em_edicao): ?>
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Editar Voo</h2>
      <form method="post" action="admin-voos.php?editar=<?= (int) $voo_em_edicao['id'] ?>">
        <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
        <input type="hidden" name="acao" value="voo_editar">
        <input type="hidden" name="id" value="<?= (int) $voo_em_edicao['id'] ?>">

        <div class="campo-form">
          <label>Número do Voo</label>
          <input name="numero_voo" value="<?= $valor_voo('numero_voo') ?>" required>
        </div>

        <div class="campo-form">
          <label>Trajeto</label>
          <select name="id_trajeto" required>
            <?php foreach ($trajetos as $trajeto): ?>
              <?php $codigo_trajeto_opcao = codificar_trajeto($trajeto['origem'], $trajeto['destino'], $trajeto['h_partida']); ?>
              <option value="<?= esc($codigo_trajeto_opcao) ?>" <?= $trajeto_escolhido === $codigo_trajeto_opcao ? 'selected' : '' ?>>
                <?= esc($trajeto['origem']) ?> → <?= esc($trajeto['destino']) ?>
                (<?= esc(substr($trajeto['h_partida'], 0, 5)) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo-form">
          <label>Aeronave</label>
          <select name="id_aeronave" required>
            <?php foreach ($aeronaves as $aeronave): ?>
              <option value="<?= $aeronave['id'] ?>" <?= $valor_voo('aeronave_id') === (string) $aeronave['id'] ? 'selected' : '' ?>>
                <?= esc($aeronave['identificacao']) ?> (<?= $aeronave['capacidade'] ?> assentos)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo-form">
          <label>Data do Voo</label>
          <input name="data_voo" type="date" value="<?= $valor_voo('data_voo') ?>" required>
        </div>

        <div class="campo-form">
          <label>Valor Base (R$)</label>
          <input name="valor_base" type="number" min="0" step="0.01" value="<?= $valor_voo('valor_base') ?>" required>
        </div>

        <?php foreach ([['piloto', 'Piloto', $pilotos_lista], ['copiloto', 'Copiloto', $copilotos_lista], ['comissario1', 'Comissário de Bordo 1', $comissarios_lista], ['comissario2', 'Comissário de Bordo 2', $comissarios_lista]] as [$campo, $rotulo, $lista]): ?>
          <div class="campo-form">
            <label><?= esc($rotulo) ?></label>
            <select name="<?= esc($campo) ?>">
              <option value="">Nenhum</option>
              <?php foreach ($lista as $pessoa): ?>
                <option value="<?= esc($pessoa['cpf']) ?>" <?= $valor_equipe($campo) === $pessoa['cpf'] ? 'selected' : '' ?>>
                  <?= esc($pessoa['nome_completo']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>

        <button type="submit" class="botao botao-bloco">Salvar Alterações</button>
        <a class="botao-pequeno" href="admin-voos.php" style="margin-top: 10px; width: 100%;">Cancelar</a>
      </form>
    </div>
    <?php else: ?>
    <!-- Neste formulário a gente cadastra um novo voo. -->
    <div class="cartao-formulario">
      <h2 class="titulo-formulario">Criar Voo</h2>
      <form method="post">
        <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
        <input type="hidden" name="acao" value="voo">
        
        <div class="campo-form">
          <label>Número do Voo</label>
          <input name="numero_voo" required>
        </div>
        
        <div class="campo-form">
          <label>Trajeto</label>
          <select name="id_trajeto" required>
            <option value="">Selecione...</option>
            <?php foreach ($trajetos as $trajeto): ?>
              <option value="<?= esc(codificar_trajeto($trajeto['origem'], $trajeto['destino'], $trajeto['h_partida'])) ?>">
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
          <input name="data_voo" type="date" required>
        </div>
        
        <div class="campo-form">
          <label>Valor Base (R$)</label>
          <input name="valor_base" type="number" min="0" step="0.01" required>
        </div>
        
        <button type="submit" class="botao botao-bloco">Criar Voo</button>
      </form>
    </div>
    
    <?php endif; ?>

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
        </div>
        
        <button type="submit" class="botao botao-bloco">Alocar Tripulante</button>
      </form>
    </div>
  </div>
  
  <!-- Aqui a gente acompanha o status e a equipe de cada voo. -->
  <div class="cartao-formulario cartao-operacional" id="lista-voos" style="scroll-margin-top: 90px;">
    <h2 class="titulo-formulario">Status Operacional dos Voos</h2>
    <?php if ($mensagem_sucesso && $aviso_na_lista): ?>
    <div class="aviso aviso-sucesso">
      <?= esc($mensagem_sucesso && $aviso_na_lista) ?>
    </div>
    <?php endif; ?>
  
    <?php if ($mensagem_erro && $aviso_na_lista): ?>
    <div class="aviso aviso-erro">
      <?= esc($mensagem_erro && $aviso_na_lista) ?>
    </div>
    <?php endif; ?>
    <p class="texto-ajuda">Requerido: 1 piloto · 1 copiloto · 2 comissários</p>
    
    <form method="get" action="admin-voos.php#lista-voos" class="flex gap-2" style="margin-bottom: 15px;">
      <?php if ($voo_em_edicao): ?>
        <input type="hidden" name="editar" value="<?= (int) $voo_em_edicao['id'] ?>">
      <?php endif; ?>
      <div class="campo-form" style="flex: 1; margin: 0;">
        <input type="search" name="busca" value="<?= esc($busca) ?>" placeholder="Buscar por voo, rota, aeronave ou data">
      </div>
      <button type="submit" class="botao-pequeno">Buscar</button>
    </form>

    <?php if(count($voos_listados) > 0): ?>
      <table class="tabela-operacional">
        <thead>
          <tr>
            <th>Voo</th>
            <th>Rota</th>
            <th>Partida</th>
            <th>Equipe</th>
            <th>Situação</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($voos_listados as $voo): ?>
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
              <td>
                <?php if ((int) $voo['passagens'] > 0): ?>
                  <span class="texto-secundario texto-pequeno">Em uso · <?= esc(descrever_passagens((int) $voo['passagens'])) ?></span>
                <?php else: ?>
                  <div class="flex gap-2">
                    <a class="botao-pequeno" href="admin-voos.php?editar=<?= (int) $voo['id'] ?>">Editar</a>
                    <form method="post" data-confirmar="Excluir o voo <?= esc($voo['numero_voo']) ?>?">
                      <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
                      <input type="hidden" name="acao" value="voo_excluir">
                      <input type="hidden" name="id" value="<?= (int) $voo['id'] ?>">
                      <button type="submit" class="botao-pequeno botao-perigo">Excluir</button>
                    </form>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="texto-vazio"><?= $busca !== '' ? 'Nenhum voo encontrado.' : 'Nenhum voo cadastrado.' ?></p>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
    const selecaoFuncao = document.querySelector('#funcao-tripulante');
    const selecaoTripulante = document.querySelector('#cpf-tripulante');

    if (!selecaoFuncao || !selecaoTripulante) {
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
            return;
        }

        correspondentes.forEach(tripulante => {
            selecaoTripulante.add(new Option(tripulante.nome, tripulante.cpf));
        });

        selecaoTripulante.disabled = false;
    }

    selecaoFuncao.addEventListener('change', atualizarTripulantes);
    atualizarTripulantes();
})();
</script>

<?php renderizar_modal_exclusao(); ?>
<?php renderizar_rodape(); ?>
