<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function resposta_json(array $conteudo, int $codigo_status = 200): never
{
    http_response_code($codigo_status);
    
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode(
        $conteudo,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    
    exit;
}

function obter_token_csrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['token_csrf'];
}

function csrf_token(): string
{
    return obter_token_csrf();
}

function verificar_token_csrf(?string $token): void
{
    if (!$token) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
    }
    
    if (!hash_equals($_SESSION['token_csrf'] ?? '', $token)) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function verify_csrf(?string $token): void
{
    verificar_token_csrf($token);
}

function obter_usuario_atual(PDO $conexao): ?array
{
    $cpf = $_SESSION['cpf_usuario'] ?? null;
    
    if (!$cpf) {
        return null;
    }

    $comando = $conexao->prepare(
        'SELECT cpf, nome_completo, email, endereco FROM usuario WHERE cpf = ?'
    );
    $comando->execute([$cpf]);
    $usuario = $comando->fetch();

    if (!$usuario) {
        return null;
    }

    $consulta_papeis = "
        SELECT 'gerente' AS papel FROM gerente WHERE cpf = ?
        UNION ALL
        SELECT 'tripulante' FROM tripulante WHERE cpf = ?
        UNION ALL
        SELECT 'admin' FROM admin WHERE cpf = ?
        UNION ALL
        SELECT 'cliente' FROM cliente WHERE cpf = ?
        ORDER BY papel
    ";
    $comando_papeis = $conexao->prepare($consulta_papeis);
    $comando_papeis->execute([$cpf, $cpf, $cpf, $cpf]);

    $usuario['papeis'] = array_column($comando_papeis->fetchAll(), 'papel');
    $usuario['papel_atual'] = $_SESSION['papel_usuario'] ?? null;

    return $usuario;
}

function verificar_perfil_login_existe(PDO $conexao, string $cpf, string $papel): bool
{
    $papeis_permitidos = ['gerente', 'tripulante', 'admin', 'cliente'];

    if (!in_array($papel, $papeis_permitidos, true)) {
        return false;
    }

    $comando = $conexao->prepare("SELECT 1 FROM {$papel} WHERE cpf = ?");
    $comando->execute([$cpf]);

    return (bool) $comando->fetchColumn();
}

function exigir_autenticacao(PDO $conexao, ?string $papel = null): array
{
    $usuario = obter_usuario_atual($conexao);

    if (!$usuario) {
        if (in_array($papel, ['admin', 'gerente', 'tripulante'], true)) {
            header('Location: login-administrativo.php');
        } else {
            header('Location: login-cliente.php');
        }
        exit;
    }

    if ($papel && ($_SESSION['papel_usuario'] ?? null) !== $papel) {
        http_response_code(403);
        exit('O perfil ativo não possui permissão para esta função. Faça login no perfil correto.');
    }

    return $usuario;
}

function require_auth(PDO $conexao, ?string $papel = null): array
{
    return exigir_autenticacao($conexao, $papel);
}

function exigir_papel(PDO $conexao, string $papel): array
{
    return exigir_autenticacao($conexao, $papel);
}

function require_role(PDO $conexao, string $papel): array
{
    return exigir_papel($conexao, $papel);
}

function registrar_acao(
    PDO $conexao,
    string $acao,
    string $descricao,
    ?string $registro_afetado = null,
    ?string $cpf_usuario = null
): void {
    $cpf_usuario ??= ($_SESSION['cpf_usuario'] ?? '');

    if (!$cpf_usuario) {
        return;
    }

    $comando = $conexao->prepare(
        'INSERT INTO log (usuario_cpf, acao, descricao, registro_afetado) 
         VALUES (?, ?, ?, ?)'
    );

    $comando->execute([$cpf_usuario, $acao, $descricao, $registro_afetado]);
}

function log_action(
    PDO $conexao,
    string $acao,
    string $descricao,
    ?string $registro_afetado = null,
    ?string $cpf_usuario = null
): void {
    registrar_acao($conexao, $acao, $descricao, $registro_afetado, $cpf_usuario);
}

function formatar_moeda_brl(float|int|string $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function format_brl(float|int|string $valor): string
{
    return formatar_moeda_brl($valor);
}

function formatar_data_hora_br(?string $valor, bool $com_data = true): string
{
    if (!$valor) {
        return '—';
    }

    $data = new DateTime($valor);

    return $com_data ? $data->format('d/m/Y · H:i') : $data->format('H:i');
}

function normalizar_data(?string $valor): ?string
{
    if (!$valor) {
        return null;
    }

    $data = DateTime::createFromFormat('Y-m-d', $valor);

    if ($data && $data->format('Y-m-d') === $valor) {
        return $valor;
    }

    return null;
}

function eh_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_post(): bool
{
    return eh_post();
}

function reservar_passagem(PDO $conexao, string $cpf_cliente, int $id_voo, int $id_assento): array
{
    $conexao->beginTransaction();

    try {
        $cmd = $conexao->prepare(
            "SELECT v.id, v.valor_base, a.id AS aeronave_id
             FROM voo v
             JOIN aeronave a ON a.id = v.aeronave_id
             WHERE v.id = ? AND v.status <> 'cancelado'
             FOR UPDATE"
        );
        $cmd->execute([$id_voo]);
        $voo = $cmd->fetch();

        if (!$voo) throw new Exception('Voo não disponível.');

        $cmd = $conexao->prepare(
            'SELECT id, codigo_assento
             FROM assento
             WHERE id = ? AND aeronave_id = ?
             FOR UPDATE'
        );
        $cmd->execute([$id_assento, $voo['aeronave_id']]);
        $assento = $cmd->fetch();

        if (!$assento) throw new Exception('Assento inválido.');

        $cmd = $conexao->prepare(
            "SELECT id FROM passagem
             WHERE voo_id = ? AND assento_id = ? AND status IN ('confirmada', 'checkin')
             FOR UPDATE"
        );
        $cmd->execute([$id_voo, $id_assento]);

        if ($cmd->fetch()) throw new Exception('Assento já reservado.');

        $numero_bilhete = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
        $cmd = $conexao->prepare(
            "INSERT INTO passagem (numero_bilhete, cliente_cpf, voo_id, assento_id, valor, status, versao)
             VALUES (?, ?, ?, ?, ?, 'confirmada', 1)"
        );
        $cmd->execute([$numero_bilhete, $cpf_cliente, $id_voo, $id_assento, $voo['valor_base']]);

        registrar_acao($conexao, 'RESERVA_CRIADA', "Passagem {$numero_bilhete} confirmada.", $numero_bilhete, $cpf_cliente);

        $conexao->commit();

        return ['ticket' => $numero_bilhete, 'codigo_assento' => $assento['codigo_assento']];
    } catch (Throwable $e) {
        if ($conexao->inTransaction()) $conexao->rollBack();

        $mensagem = $e->getMessage();
        if (
            $e instanceof PDOException
            && (int) ($e->errorInfo[1] ?? 0) === 1062
            && strpos((string) ($e->errorInfo[2] ?? ''), 'uq_passagem_assento_voo') !== false
        ) {
            $mensagem = 'Este assento já foi reservado. Escolha outro assento.';
        }

        throw new RuntimeException($mensagem, 0, $e);
    }
}

const LIMITE_HORAS_TRABALHO = 12;

const DESCANSO_MINIMO_HORAS = 36;

function avaliar_escala_tripulante(array $escalas_existentes, string $partida, string $chegada): ?string
{
    $inicio_novo = strtotime($partida);
    $fim_novo = strtotime($chegada);

    foreach ($escalas_existentes as $voo) {
        if (strtotime($voo['partida_em']) < $fim_novo && strtotime($voo['chegada_em']) > $inicio_novo) {
            return "O tripulante já está escalado no voo {$voo['numero_voo']} nesse horário.";
        }
    }

    $voos = [];
    foreach ($escalas_existentes as $voo) {
        $voos[] = ['inicio' => strtotime($voo['partida_em']), 'fim' => strtotime($voo['chegada_em']), 'novo' => false];
    }
    $voos[] = ['inicio' => $inicio_novo, 'fim' => $fim_novo, 'novo' => true];
    usort($voos, static fn(array $a, array $b): int => $a['inicio'] <=> $b['inicio']);

    $periodos = [];
    $periodo = [];
    $fim_anterior = null;

    foreach ($voos as $voo) {
        if ($fim_anterior !== null && $voo['inicio'] - $fim_anterior >= DESCANSO_MINIMO_HORAS * 3600) {
            $periodos[] = $periodo;
            $periodo = [];
        }

        $periodo[] = $voo;
        $fim_anterior = $voo['fim'];
    }
    $periodos[] = $periodo;

    foreach ($periodos as $periodo) {
        $inclui_novo = in_array(true, array_column($periodo, 'novo'), true);
        if (!$inclui_novo) {
            continue;
        }

        $segundos = array_sum(array_map(static fn(array $voo): int => $voo['fim'] - $voo['inicio'], $periodo));
        if ($segundos > LIMITE_HORAS_TRABALHO * 3600) {
            $horas = number_format($segundos / 3600, 1, ',', '.');
            return 'Este voo levaria o tripulante a ' . $horas . ' horas de trabalho sem o descanso de '
                . DESCANSO_MINIMO_HORAS . ' horas, acima do limite de ' . LIMITE_HORAS_TRABALHO . ' horas.';
        }
    }

    return null;
}

function definir_aviso(string $texto, string $tipo = 'sucesso'): void
{
    $_SESSION['aviso'] = ['texto' => $texto, 'tipo' => $tipo];
}

function obter_aviso(): ?array
{
    $aviso = $_SESSION['aviso'] ?? null;
    unset($_SESSION['aviso']);

    return $aviso;
}
