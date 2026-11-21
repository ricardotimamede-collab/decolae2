<?php

/**
 * Aqui a gente reúne funções usadas em várias partes do sistema.
 * 
 * Elas cuidam de segurança, formatação, respostas da API e registros de ações.
 */

declare(strict_types=1);

// Aqui a gente carrega a conexão que as funções abaixo usam.
require_once __DIR__ . '/db.php';

/**
 * Aqui a gente escapa caracteres especiais antes de exibir um valor no HTML.
 * 
 * @param mixed $valor Valor a ser escapado
 * @return string Valor escapado seguro para HTML
 */
function esc(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Aqui a gente envia uma resposta JSON e encerra a requisição.
 * 
 * O status HTTP e o cabeçalho são definidos junto com a resposta.
 * 
 * @param array $conteudo Dados a serem retornados em JSON
 * @param int $codigo_status Código HTTP (padrão: 200)
 * @return never Encerra a execução
 */
function resposta_json(array $conteudo, int $codigo_status = 200): never
{
    // Primeiro a gente informa o status da resposta.
    http_response_code($codigo_status);
    
    // Depois avisamos que o conteúdo será JSON.
    header('Content-Type: application/json; charset=utf-8');
    
    // Assim os acentos e outros caracteres ficam legíveis na resposta.
    echo json_encode(
        $conteudo,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    
    // A resposta já foi enviada, então a gente encerra aqui.
    exit;
}

/**
 * Aqui a gente recupera o token CSRF da sessão ou cria um novo.
 * 
 * O token fica salvo na sessão para ser conferido quando o formulário voltar.
 * 
 * @return string Token CSRF em hexadecimal
 */
function obter_token_csrf(): string
{
    // Se ainda não houver token na sessão, a gente cria um.
    if (empty($_SESSION['token_csrf'])) {
        // Esses bytes aleatórios formam um token difícil de adivinhar.
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['token_csrf'];
}

/**
 * Aqui a gente mantém o nome curto usado por páginas antigas.
 * 
 * @return string Token CSRF em hexadecimal
 */
function csrf_token(): string
{
    return obter_token_csrf();
}

/**
 * Aqui a gente confere se o token recebido pertence à sessão atual.
 * 
 * A comparação segura evita diferenças de tempo que poderiam revelar o token.
 * 
 * @param string|null $token Token CSRF a validar
 * @return void
 * @throws RuntimeException Se token for inválido ou ausente
 */
function verificar_token_csrf(?string $token): void
{
    // Sem token, não dá para validar o formulário.
    if (!$token) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
    }
    
    // A gente compara os valores sem expor diferenças no tempo de resposta.
    if (!hash_equals($_SESSION['token_csrf'] ?? '', $token)) {
        throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
    }
}

/**
 * Aqui a gente mantém o nome antigo para os formulários que ainda o usam.
 * 
 * @param string|null $token Token CSRF a validar
 * @return void
 */
function verify_csrf(?string $token): void
{
    verificar_token_csrf($token);
}

/**
 * Aqui a gente busca os dados e os perfis de quem está conectado.
 * 
 * Os perfis possíveis são gerente, tripulante, administrador e cliente.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @return array|null Array com dados do usuário ou null se não autenticado
 */
function obter_usuario_atual(PDO $conexao): ?array
{
    // Primeiro a gente identifica quem está conectado.
    $cpf = $_SESSION['cpf_usuario'] ?? null;
    
    // Sem CPF na sessão, não há usuário para buscar.
    if (!$cpf) {
        return null;
    }

    // Agora a gente carrega os dados principais da conta.
    $comando = $conexao->prepare(
        'SELECT cpf, nome_completo, email, endereco FROM usuario WHERE cpf = ?'
    );
    $comando->execute([$cpf]);
    $usuario = $comando->fetch();

    // A conta pode ter sido removida; nesse caso, não seguimos.
    if (!$usuario) {
        return null;
    }

    // Aqui a gente reúne todos os perfis vinculados à conta.
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

    // Juntamos os perfis encontrados com o perfil ativo na sessão.
    $usuario['papeis'] = array_column($comando_papeis->fetchAll(), 'papel');
    $usuario['papel_atual'] = $_SESSION['papel_usuario'] ?? null;

    return $usuario;
}

/**
 * Aqui a gente confere se a conta está cadastrada no perfil informado.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string $cpf CPF do usuário
 * @param string $papel Papel a verificar (gerente, tripulante, admin, cliente)
 * @return bool True se o usuário existe no papel especificado
 */
function verificar_perfil_login_existe(PDO $conexao, string $cpf, string $papel): bool
{
    // Só consultamos os perfis que existem no sistema.
    $papeis_permitidos = ['gerente', 'tripulante', 'admin', 'cliente'];

    // Se o perfil não for permitido, não consultamos a tabela.
    if (!in_array($papel, $papeis_permitidos, true)) {
        return false;
    }

    // O nome da tabela vem apenas da lista de perfis permitidos acima.
    $comando = $conexao->prepare("SELECT 1 FROM {$papel} WHERE cpf = ?");
    $comando->execute([$cpf]);

    return (bool) $comando->fetchColumn();
}

/**
 * Aqui a gente exige uma sessão válida antes de abrir a página.
 * 
 * Quando a página pede um perfil, a gente também confere se ele está ativo.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string|null $papel Papel requerido (opcional)
 * @return array Dados do usuário autenticado
 * @throws RuntimeException Se não autenticado ou papel incorreto
 */
function exigir_autenticacao(PDO $conexao, ?string $papel = null): array
{
    // Primeiro a gente verifica quem está conectado.
    $usuario = obter_usuario_atual($conexao);

    // Sem sessão válida, a pessoa volta para a tela de login correspondente.
    if (!$usuario) {
        // Cada tipo de conta usa sua própria tela de entrada.
        if (in_array($papel, ['admin', 'gerente', 'tripulante'], true)) {
            header('Location: login-administrativo.php');
        } else {
            header('Location: login-cliente.php');
        }
        exit;
    }

    // Se a página exige um perfil, ele precisa ser o perfil ativo.
    if ($papel && ($_SESSION['papel_usuario'] ?? null) !== $papel) {
        http_response_code(403);
        exit('O perfil ativo não possui permissão para esta função. Faça login no perfil correto.');
    }

    return $usuario;
}

/**
 * Aqui a gente preserva o nome em inglês usado por páginas antigas.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string|null $papel Papel requerido
 * @return array Dados do usuário autenticado
 */
function require_auth(PDO $conexao, ?string $papel = null): array
{
    return exigir_autenticacao($conexao, $papel);
}

/**
 * Aqui a gente exige que a pessoa esteja usando o perfil indicado.
 * 
 * É um atalho para chamar a validação de autenticação com esse perfil.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string $papel Papel requerido
 * @return array Dados do usuário autenticado
 */
function exigir_papel(PDO $conexao, string $papel): array
{
    return exigir_autenticacao($conexao, $papel);
}

/**
 * Aqui a gente mantém o nome em inglês para não quebrar chamadas antigas.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string $papel Papel requerido
 * @return array Dados do usuário autenticado
 */
function require_role(PDO $conexao, string $papel): array
{
    return exigir_papel($conexao, $papel);
}

/**
 * Aqui a gente guarda no log uma ação feita por um usuário.
 * 
 * Esse registro ajuda a acompanhar alterações feitas no sistema.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string $acao Tipo de ação (ex: TRAJETO_CRIADO, AERONAVE_CRIADA)
 * @param string $descricao Descrição detalhada da ação
 * @param string|null $registro_afetado Identificador do registro afetado (opcional)
 * @param string|null $cpf_usuario CPF do usuário (opcional, usa sessão se não fornecido)
 * @return void
 */
function registrar_acao(
    PDO $conexao,
    string $acao,
    string $descricao,
    ?string $registro_afetado = null,
    ?string $cpf_usuario = null
): void {
    // Se não recebemos um CPF, a gente usa o da sessão atual.
    $cpf_usuario ??= ($_SESSION['cpf_usuario'] ?? '');

    // Sem usuário identificado, não há ação para registrar.
    if (!$cpf_usuario) {
        return;
    }

    // Aqui a gente prepara o registro da ação.
    $comando = $conexao->prepare(
        'INSERT INTO log (usuario_cpf, acao, descricao, registro_afetado) 
         VALUES (?, ?, ?, ?)'
    );

    // E salva a ação no banco.
    $comando->execute([$cpf_usuario, $acao, $descricao, $registro_afetado]);
}

/**
 * Aqui a gente mantém o nome usado por páginas antigas.
 * 
 * @param PDO $conexao Conexão com banco de dados
 * @param string $acao Tipo de ação
 * @param string $descricao Descrição detalhada
 * @param string|null $registro_afetado Identificador do registro afetado
 * @param string|null $cpf_usuario CPF do usuário
 * @return void
 */
function log_action(
    PDO $conexao,
    string $acao,
    string $descricao,
    ?string $registro_afetado = null,
    ?string $cpf_usuario = null
): void {
    registrar_acao($conexao, $acao, $descricao, $registro_afetado, $cpf_usuario);
}

/**
 * Aqui a gente mostra um valor no formato de moeda brasileira.
 * 
 * A vírgula separa os centavos e o ponto separa os milhares.
 * 
 * @param float|int|string $valor Valor a formatar
 * @return string Valor formatado como "R$ 1.234,56"
 */
function formatar_moeda_brl(float|int|string $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/**
 * Aqui a gente mantém o nome em inglês usado por páginas antigas.
 * 
 * @param float|int|string $valor Valor a formatar
 * @return string Valor formatado como "R$ 1.234,56"
 */
function format_brl(float|int|string $valor): string
{
    return formatar_moeda_brl($valor);
}

/**
 * Aqui a gente apresenta uma data e hora no formato usado no Brasil.
 * 
 * @param string|null $valor Data/hora em formato ISO (YYYY-MM-DD HH:MM:SS)
 * @param bool $com_data Se true, inclui data; se false, apenas hora
 * @return string Data/hora formatada (ex: "21/11/2026 · 14:30" ou "14:30")
 */
function formatar_data_hora_br(?string $valor, bool $com_data = true): string
{
    // Sem valor, a gente mostra um travessão no lugar.
    if (!$valor) {
        return '—';
    }

    // O DateTime ajuda a formatar a data recebida.
    $data = new DateTime($valor);

    // A gente inclui a data só quando a tela pedir.
    return $com_data ? $data->format('d/m/Y · H:i') : $data->format('H:i');
}

/**
 * Aqui a gente valida a data e devolve no formato aceito pelo banco.
 * 
 * O valor precisa estar no formato ano-mês-dia.
 * 
 * @param string|null $valor Data a normalizar
 * @return string|null Data em formato YYYY-MM-DD ou null se inválida
 */
function normalizar_data(?string $valor): ?string
{
    // Sem data preenchida, não há valor para normalizar.
    if (!$valor) {
        return null;
    }

    // Primeiro a gente tenta interpretar o formato esperado.
    $data = DateTime::createFromFormat('Y-m-d', $valor);

    // Só aceitamos a data se ela voltar exatamente no mesmo formato.
    if ($data && $data->format('Y-m-d') === $valor) {
        return $valor;
    }

    // Se chegou até aqui, a data não é válida.
    return null;
}

/**
 * Aqui a gente confere se a requisição atual foi enviada por POST.
 * 
 * @return bool True se método HTTP é POST
 */
function eh_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * Aqui a gente mantém o nome em inglês para chamadas antigas.
 * 
 * @return bool True se método HTTP é POST
 */
function is_post(): bool
{
    return eh_post();
}
