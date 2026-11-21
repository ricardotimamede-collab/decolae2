<?php

declare(strict_types=1);

// Aqui a gente avisa que a resposta será JSON antes de enviar qualquer conteúdo.
header('Content-Type: application/json; charset=utf-8');

// A consulta usa as funções compartilhadas do sistema.
require_once __DIR__ . '/../includes/functions.php';

try {
    // Primeiro a gente confere se o identificador do voo é válido.
    $id_voo = filter_input(INPUT_GET, 'id_voo', FILTER_VALIDATE_INT);
    if (!$id_voo) resposta_json(['ok' => false, 'message' => 'Voo inválido.'], 422);
    
    // A conexão vai buscar o voo e seus assentos.
    $conexao_pdo = db();
    
    // Aqui a gente carrega os dados do voo escolhido.
    $comando = $conexao_pdo->prepare(
        "SELECT v.id, v.numero_voo, 
                TIMESTAMP(v.data_voo, t.h_partida) AS partida_em, 
                TIMESTAMP(v.data_voo, t.h_chegada) AS chegada_em, 
                v.valor_base, v.aeronave_id, a.modelo, a.identificacao, 
                t.origem, t.destino 
         FROM voo v 
         JOIN aeronave a ON a.id = v.aeronave_id 
         JOIN trajeto t ON t.id = v.trajeto_id 
         WHERE v.id = ? AND v.status <> 'cancelado'"
    );
    $comando->execute([$id_voo]);
    $voo = $comando->fetch();
    
    if (!$voo) resposta_json(['ok' => false, 'message' => 'Voo não encontrado.'], 404);
    
    // Agora a gente lista os assentos dessa aeronave e verifica quais estão ocupados.
    $comando = $conexao_pdo->prepare(
        "SELECT s.id, s.codigo_assento, s.numero_fileira, s.letra, 
                CASE 
                    WHEN p.id IS NULL THEN 'livre' 
                    ELSE 'ocupado' 
                END AS status 
         FROM assento s 
         LEFT JOIN passagem p 
            ON p.assento_id = s.id 
            AND p.voo_id = ? 
            AND p.status IN ('confirmada', 'checkin') 
         WHERE s.aeronave_id = ? 
         ORDER BY s.numero_fileira, s.letra"
    );
    $comando->execute([$id_voo, $voo['aeronave_id']]);
    $assentos = $comando->fetchAll();
    
    // A gente confere quantas pessoas já foram escaladas em cada função.
    $comando = $conexao_pdo->prepare(
        "SELECT SUM(funcao_tripulante = 'piloto') AS pilotos,
                SUM(funcao_tripulante = 'copiloto') AS copilotos,
                SUM(funcao_tripulante = 'comissario') AS comissarios 
         FROM escala 
         WHERE voo_id = ?"
    );
    $comando->execute([$id_voo]);
    $dados_tripulacao = $comando->fetch() ?: [];
    
    // A reserva só fica disponível quando a equipe mínima está completa.
    $voo['equipe_completa'] = (
        (int) ($dados_tripulacao['pilotos'] ?? 0) >= 1 && 
        (int) ($dados_tripulacao['copilotos'] ?? 0) >= 1 && 
        (int) ($dados_tripulacao['comissarios'] ?? 0) >= 2
    );
    
    // Com os dados prontos, a gente responde em JSON.
    resposta_json(['ok' => true, 'flight' => $voo, 'seats' => $assentos]);
} catch (Throwable $e) {
    resposta_json(['ok' => false, 'message' => 'Não foi possível carregar os assentos.'], 500);
}
