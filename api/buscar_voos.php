<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    $conexao_pdo = db();
    
    $origem = trim((string) ($_GET['origin'] ?? ''));
    $destino = trim((string) ($_GET['destination'] ?? ''));
    $data_voo = normalizar_data(isset($_GET['date']) ? (string) $_GET['date'] : null);
    
    $sql = "SELECT * FROM vw_voos_disponibilidade WHERE status = 'programado' AND partida_em > ?
            AND id IN (
                SELECT voo_id FROM escala GROUP BY voo_id
                HAVING SUM(funcao_tripulante = 'piloto') >= 1
                   AND SUM(funcao_tripulante = 'copiloto') >= 1
                   AND SUM(funcao_tripulante = 'comissario') >= 2
            )";
    $parametros = [date('Y-m-d H:i:s')];
    
    if ($origem !== '') {
        $sql .= " AND origem LIKE ? ESCAPE '!'";
        $parametros[] = '%' . strtr($origem, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    
    if ($destino !== '') {
        $sql .= " AND destino LIKE ? ESCAPE '!'";
        $parametros[] = '%' . strtr($destino, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    
    if ($data_voo !== null) {
        $sql .= " AND data_voo = ?";
        $parametros[] = $data_voo;
    }
    
    $sql .= " ORDER BY partida_em LIMIT 50";
    
    $comando = $conexao_pdo->prepare($sql);
    $comando->execute($parametros);
    
    resposta_json(['ok' => true, 'voos' => $comando->fetchAll()]);
} catch (Throwable $e) {
    resposta_json(['ok' => false, 'message' => 'Erro ao buscar voos.'], 500);
}
