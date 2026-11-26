<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$log_file = __DIR__ . '/../storage/cancelar_bilhete_detalhado.log';
$timestamp = date('Y-m-d H:i:s');

$log = "\n" . str_repeat('=', 60) . "\n";
$log .= "[$timestamp] REQUISIÇÃO DE CANCELAMENTO (DETALHADO)\n";
$log .= str_repeat('=', 60) . "\n";

$log .= "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$log .= "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'indefinido') . "\n";
$log .= "Session ID: " . session_id() . "\n";

$log .= "\nAutenticação:\n";
$log .= "  cpf_usuario: " . ($_SESSION['cpf_usuario'] ?? 'NÃO DEFINIDO') . "\n";
$log .= "  papel_usuario: " . ($_SESSION['papel_usuario'] ?? 'NÃO DEFINIDO') . "\n";

if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
    $log .= "  RESULTADO: FALHA\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Não autorizado']);
    exit;
}

$log .= "  RESULTADO: OK\n";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $log .= "\nMétodo inválido\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método não permitido']);
    exit;
}

$raw_input = file_get_contents('php://input');
$log .= "\nInput recebido:\n";
$log .= "  Raw: " . substr($raw_input, 0, 100) . "...\n";
$log .= "  Tamanho: " . strlen($raw_input) . " bytes\n";

$dados = json_decode($raw_input, true);
$log .= "  JSON parseado: " . json_encode($dados) . "\n";

if (!isset($dados['id_passagem'])) {
    $log .= "  ERRO: id_passagem não fornecido\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID da passagem é obrigatório']);
    exit;
}

$id_passagem = (int) $dados['id_passagem'];
$cpf_usuario = $_SESSION['cpf_usuario'];

$log .= "\nProcessando:\n";
$log .= "  ID: " . $id_passagem . "\n";
$log .= "  CPF: " . $cpf_usuario . "\n";

try {
    $conexao_pdo = db();
    
    $comando = $conexao_pdo->prepare(
        "SELECT p.id, p.voo_id, p.status, p.numero_bilhete 
         FROM passagem p
         WHERE p.id = ? AND p.cliente_cpf = ?"
    );
    
    $log .= "\nExecutando SELECT...\n";
    $exec_result = $comando->execute([$id_passagem, $cpf_usuario]);
    $log .= "  Execute result: " . ($exec_result ? 'true' : 'false') . "\n";
    
    $passagem = $comando->fetch();
    $log .= "  Bilhete encontrado: " . ($passagem ? 'SIM' : 'NÃO') . "\n";
    
    if (!$passagem) {
        $log .= "  ERRO: Bilhete não encontrado\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Bilhete não encontrado']);
        exit;
    }
    
    $log .= "  Status: " . $passagem['status'] . "\n";
    
    if ($passagem['status'] === 'cancelada') {
        $log .= "  ERRO: Bilhete já cancelado\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Este bilhete já foi cancelado']);
        exit;
    }
    
    $log .= "\nExecutando UPDATE...\n";
    
    $conexao_pdo->beginTransaction();

    $comando = $conexao_pdo->prepare(
        "UPDATE passagem SET status = 'cancelada', data_cancelamento = NOW() WHERE id = ?"
    );
    
    $update_result = $comando->execute([$id_passagem]);
    $log .= "  Execute result: " . ($update_result ? 'true' : 'false') . "\n";
    $log .= "  Rows affected: " . $comando->rowCount() . "\n";
    
    if (!$update_result || $comando->rowCount() === 0) {
        $conexao_pdo->rollBack();
        $log .= "  ERRO: UPDATE falhou\n";
        $log .= "  Error info: " . implode(', ', $comando->errorInfo()) . "\n";
        file_put_contents($log_file, $log, FILE_APPEND);
        
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Erro ao cancelar bilhete']);
        exit;
    }
    
    registrar_acao(
        $conexao_pdo,
        'RESERVA_CANCELADA',
        "Passagem {$passagem['numero_bilhete']} cancelada.",
        $passagem['numero_bilhete'],
        $cpf_usuario
    );

    $conexao_pdo->commit();

    $log .= "\nSUCESSO!\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'message' => 'Bilhete cancelado com sucesso'
    ]);
    
} catch (Throwable $e) {
    if (isset($conexao_pdo) && $conexao_pdo->inTransaction()) {
        $conexao_pdo->rollBack();
    }

    $log .= "\nEXCEÇÃO:\n";
    $log .= "  Mensagem: " . $e->getMessage() . "\n";
    $log .= "  Arquivo: " . $e->getFile() . "\n";
    $log .= "  Linha: " . $e->getLine() . "\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit;
}
