<?php
declare(strict_types=1);

// Aqui a gente avisa que a resposta será JSON antes de enviar qualquer conteúdo.
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/functions.php';

try {
    if (!eh_post()) resposta_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
    verificar_token_csrf($_POST['token_csrf'] ?? null);
    
    $conexao_pdo = db();
    $usuario = obter_usuario_atual($conexao_pdo);
    
    if (!$usuario || ($_SESSION['papel_usuario'] ?? null) !== 'cliente') {
        resposta_json(['ok' => false, 'message' => 'Não autorizado.'], 403);
    }
    
    $id_voo = filter_var($_POST['id_voo'] ?? null, FILTER_VALIDATE_INT);
    $id_assento = filter_var($_POST['id_assento'] ?? null, FILTER_VALIDATE_INT);
    
    if (!$id_voo || !$id_assento) {
        resposta_json(['ok' => false, 'message' => 'Parâmetros inválidos.'], 422);
    }
    
    $conexao_pdo->beginTransaction();
    
    // O bloqueio evita que outra reserva altere este voo durante a operação.
    $cmd = $conexao_pdo->prepare(
        "SELECT v.id, v.valor_base, v.versao, a.id AS aeronave_id 
         FROM voo v 
         JOIN aeronave a ON a.id = v.aeronave_id 
         WHERE v.id = ? AND v.status <> 'cancelado' 
         FOR UPDATE"
    );
    $cmd->execute([$id_voo]);
    $voo = $cmd->fetch();
    
    if (!$voo) throw new Exception('Voo não disponível.');
    
    // Também bloqueamos o assento enquanto conferimos se ele pertence ao voo.
    $cmd = $conexao_pdo->prepare(
        'SELECT id, codigo_assento, versao 
         FROM assento 
         WHERE id = ? AND aeronave_id = ? 
         FOR UPDATE'
    );
    $cmd->execute([$id_assento, $voo['aeronave_id']]);
    $assento = $cmd->fetch();
    
    if (!$assento) throw new Exception('Assento inválido.');
    
    // Antes de reservar, a gente confirma que ninguém ocupou o assento.
    $cmd = $conexao_pdo->prepare(
        "SELECT id FROM passagem 
         WHERE voo_id = ? AND assento_id = ? AND status IN ('confirmada', 'checkin') 
         FOR UPDATE"
    );
    $cmd->execute([$id_voo, $id_assento]);
    
    if ($cmd->fetch()) throw new Exception('Assento já reservado.');
    
    // Se o assento continua livre, a gente cria a passagem.
    $numero_bilhete = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
    $cmd = $conexao_pdo->prepare(
        "INSERT INTO passagem (numero_bilhete, cliente_cpf, voo_id, assento_id, valor, status, versao) 
         VALUES (?, ?, ?, ?, ?, 'confirmada', 1)"
    );
    $cmd->execute([$numero_bilhete, $usuario['cpf'], $id_voo, $id_assento, $voo['valor_base']]);
    
    registrar_acao($conexao_pdo, 'RESERVA_CRIADA', "Passagem {$numero_bilhete} confirmada.", $numero_bilhete, $usuario['cpf']);
    
    $conexao_pdo->commit();
    resposta_json(['ok' => true, 'ticket' => $numero_bilhete, 'codigo_assento' => $assento['codigo_assento']]);
    
} catch (Throwable $e) {
    if (isset($conexao_pdo) && $conexao_pdo->inTransaction()) $conexao_pdo->rollBack();

    $mensagem = $e->getMessage();
    if (
        $e instanceof PDOException
        && (int)($e->errorInfo[1] ?? 0) === 1062
        && strpos((string)($e->errorInfo[2] ?? ''), 'uq_passagem_assento_voo') !== false
    ) {
        $mensagem = 'Este assento já foi reservado. Escolha outro assento.';
    }

    resposta_json(['ok' => false, 'message' => $mensagem], 409);
}
