<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    $id_voo = filter_input(INPUT_GET, 'id_voo', FILTER_VALIDATE_INT);
    if (!$id_voo) resposta_json(['ok' => false, 'message' => 'Voo inválido.'], 422);
    
    $conexao_pdo = db();
    
    $comando = $conexao_pdo->prepare(
        "SELECT v.id, v.numero_voo, 
                TIMESTAMP(v.data_voo, t.h_partida) AS partida_em, 
                TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY AS chegada_em, 
                v.valor_base, v.aeronave_id, a.modelo, a.identificacao, 
                t.origem, t.destino 
         FROM voo v 
         JOIN aeronave a ON a.id = v.aeronave_id 
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida 
         WHERE v.id = ? AND v.status <> 'cancelado'"
    );
    $comando->execute([$id_voo]);
    $voo = $comando->fetch();
    
    if (!$voo) resposta_json(['ok' => false, 'message' => 'Voo não encontrado.'], 404);
    
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
    
    $comando = $conexao_pdo->prepare(
        "SELECT SUM(funcao_tripulante = 'piloto') AS pilotos,
                SUM(funcao_tripulante = 'copiloto') AS copilotos,
                SUM(funcao_tripulante = 'comissario') AS comissarios 
         FROM escala 
         WHERE voo_id = ?"
    );
    $comando->execute([$id_voo]);
    $dados_tripulacao = $comando->fetch() ?: [];
    
    $voo['equipe_completa'] = (
        (int) ($dados_tripulacao['pilotos'] ?? 0) >= 1 && 
        (int) ($dados_tripulacao['copilotos'] ?? 0) >= 1 && 
        (int) ($dados_tripulacao['comissarios'] ?? 0) >= 2
    );
    
    resposta_json(['ok' => true, 'flight' => $voo, 'seats' => $assentos]);
} catch (Throwable $e) {
    resposta_json(['ok' => false, 'message' => 'Não foi possível carregar os assentos.'], 500);
}
