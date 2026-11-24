<?php
declare(strict_types=1);

/**
 * Aqui a gente devolve os bilhetes da pessoa que está conectada.
 * 
 * A resposta inclui os dados do voo, da aeronave, do assento e do trajeto.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    // Só clientes conectados podem consultar os próprios bilhetes.
    if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
        resposta_json(['ok' => false, 'message' => 'Não autorizado.'], 403);
    }
    
    $conexao_pdo = db();
    $usuario = obter_usuario_atual($conexao_pdo);
    
    if (!$usuario) {
        resposta_json(['ok' => false, 'message' => 'Usuário não encontrado.'], 404);
    }
    
    // Aqui a gente reúne os detalhes necessários para mostrar cada bilhete.
    $comando = $conexao_pdo->prepare(
        "SELECT 
            p.id,
            p.numero_bilhete,
            p.valor,
            p.status,
            p.data_compra,
            v.numero_voo,
            v.data_voo,
            v.status AS status_voo,
            a.modelo,
            a.identificacao,
            asn.codigo_assento,
            t.origem,
            t.destino,
            t.h_partida,
            t.h_chegada,
            CONCAT(v.data_voo, ' ', t.h_partida) AS partida_em,
            TIMESTAMP(v.data_voo, t.h_chegada) + INTERVAL (t.h_chegada < t.h_partida) DAY AS chegada_em
         FROM passagem p
         JOIN voo v ON v.id = p.voo_id
         JOIN trajeto t ON t.origem = v.origem AND t.destino = v.destino AND t.h_partida = v.h_partida
         JOIN aeronave a ON a.id = v.aeronave_id
         JOIN assento asn ON asn.id = p.assento_id
         WHERE p.cliente_cpf = ?
         ORDER BY p.data_compra DESC"
    );
    
    $comando->execute([$usuario['cpf']]);
    $passagens = $comando->fetchAll();
    
    resposta_json([
        'ok' => true,
        'passagens' => $passagens,
        'total' => count($passagens)
    ]);

} catch (Throwable $e) {
    resposta_json(['ok' => false, 'message' => $e->getMessage()], 500);
}
