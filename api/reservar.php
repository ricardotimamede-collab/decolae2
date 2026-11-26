<?php
declare(strict_types=1);

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

    $resultado = reservar_passagem($conexao_pdo, $usuario['cpf'], $id_voo, $id_assento);
    resposta_json(['ok' => true, 'ticket' => $resultado['ticket'], 'codigo_assento' => $resultado['codigo_assento']]);

} catch (Throwable $e) {
    resposta_json(['ok' => false, 'message' => $e->getMessage()], 409);
}
