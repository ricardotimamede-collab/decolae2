<?php

declare(strict_types=1);

// Aqui a gente carrega as funções compartilhadas e o layout da página.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

// Esta área é exclusiva para quem entrou como cliente.
if (!isset($_SESSION['cpf_usuario']) || $_SESSION['papel_usuario'] !== 'cliente') {
    header('Location: login-cliente.php');
    exit;
}

$conexao_pdo = db();
$usuario = obter_usuario_atual($conexao_pdo);

// O voo e o assento chegam pela URL e, na confirmação, pelo formulário.
$tipo_entrada = eh_post() ? INPUT_POST : INPUT_GET;
$id_voo = filter_input($tipo_entrada, 'voo', FILTER_VALIDATE_INT);
$id_assento = filter_input($tipo_entrada, 'assento', FILTER_VALIDATE_INT);

// Sem voo e assento válidos, a pessoa volta para a busca.
if (!$id_voo || !$id_assento) {
    definir_aviso('Escolha um voo e um assento para continuar.', 'erro');
    header('Location: index.php#voos');
    exit;
}

$comando = $conexao_pdo->prepare(
    "SELECT v.id, v.numero_voo, v.data_voo, v.valor_base,
            t.origem, t.destino, t.h_partida, t.h_chegada,
            a.modelo, a.identificacao,
            s.id AS assento_id, s.codigo_assento
     FROM voo v
     JOIN trajeto t ON t.id = v.trajeto_id
     JOIN aeronave a ON a.id = v.aeronave_id
     JOIN assento s ON s.aeronave_id = a.id AND s.id = ?
     WHERE v.id = ? AND v.status <> 'cancelado'"
);
$comando->execute([$id_assento, $id_voo]);
$compra = $comando->fetch();

if (!$compra) {
    definir_aviso('Voo ou assento não encontrado.', 'erro');
    header('Location: index.php#voos');
    exit;
}

// Se alguém ocupou o assento enquanto a pessoa decidia, ela escolhe outro.
$comando = $conexao_pdo->prepare(
    "SELECT 1 FROM passagem
     WHERE voo_id = ? AND assento_id = ? AND status IN ('confirmada', 'checkin')"
);
$comando->execute([$id_voo, $id_assento]);

if ($comando->fetchColumn()) {
    definir_aviso('Este assento já foi reservado. Escolha outro assento.', 'erro');
    header('Location: index.php#voos');
    exit;
}

// Na confirmação, a gente grava a compra e leva a pessoa para os bilhetes.
if (eh_post()) {
    try {
        verificar_token_csrf($_POST['token_csrf'] ?? null);
        $resultado = reservar_passagem($conexao_pdo, $usuario['cpf'], $id_voo, $id_assento);

        definir_aviso("Compra confirmada · bilhete {$resultado['ticket']} · assento {$resultado['codigo_assento']}.");
        header('Location: index.php#meus-bilhetes');
        exit;
    } catch (Throwable $excecao) {
        definir_aviso($excecao->getMessage(), 'erro');
        header('Location: index.php#voos');
        exit;
    }
}

renderizar_cabecalho('Resumo da compra', $usuario);
?>

<div class="mx-auto max-w-2xl px-5 py-10">
  <h1 class="titulo-secao-busca">Resumo da compra</h1>
  <p class="texto-secundario texto-pequeno" style="margin-bottom: 20px;">
    Confira os dados da viagem antes de confirmar.
  </p>

  <div class="cartao-busca-voos">
    <dl class="grid gap-3 text-sm" style="grid-template-columns: max-content 1fr; column-gap: 24px;">
      <dt class="font-bold">Voo</dt>
      <dd><?= esc($compra['numero_voo']) ?></dd>

      <dt class="font-bold">Origem</dt>
      <dd><?= esc($compra['origem']) ?></dd>

      <dt class="font-bold">Destino</dt>
      <dd><?= esc($compra['destino']) ?></dd>

      <dt class="font-bold">Data</dt>
      <dd><?= esc(date('d/m/Y', strtotime($compra['data_voo']))) ?></dd>

      <dt class="font-bold">Partida</dt>
      <dd><?= esc(substr($compra['h_partida'], 0, 5)) ?></dd>

      <dt class="font-bold">Chegada</dt>
      <dd><?= esc(substr($compra['h_chegada'], 0, 5)) ?></dd>

      <dt class="font-bold">Aeronave</dt>
      <dd><?= esc($compra['modelo']) ?> · <?= esc($compra['identificacao']) ?></dd>

      <dt class="font-bold">Assento</dt>
      <dd><?= esc($compra['codigo_assento']) ?></dd>

      <dt class="font-bold">Valor</dt>
      <dd><?= esc(formatar_moeda_brl($compra['valor_base'])) ?></dd>
    </dl>
  </div>

  <form method="post" class="flex gap-3">
    <input type="hidden" name="token_csrf" value="<?= esc(obter_token_csrf()) ?>">
    <input type="hidden" name="voo" value="<?= esc($id_voo) ?>">
    <input type="hidden" name="assento" value="<?= esc($id_assento) ?>">

    <a class="secondary-btn px-6 py-3" href="index.php#voos">Voltar</a>
    <button class="botao-login flex-1" type="submit">Confirmar compra</button>
  </form>
</div>

<?php renderizar_rodape(); ?>
