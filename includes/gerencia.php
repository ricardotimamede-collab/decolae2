<?php

declare(strict_types=1);

function obter_campo_obrigatorio(string $nomeCampo): string
{
    $valor = trim((string)($_POST[$nomeCampo] ?? ''));
    
    if ($valor === '') {
        throw new InvalidArgumentException('Preencha todos os campos.');
    }
    
    return $valor;
}

function codificar_trajeto(string $origem, string $destino, string $partida): string
{
    return bin2hex(json_encode([$origem, $destino, substr($partida, 0, 5) . ':00'], JSON_UNESCAPED_UNICODE));
}

function decodificar_trajeto(string $codigo): array
{
    $dados = ctype_xdigit($codigo) && strlen($codigo) % 2 === 0 ? json_decode((string) hex2bin($codigo), true) : null;

    if (!is_array($dados) || count($dados) !== 3) {
        throw new InvalidArgumentException('Trajeto não encontrado.');
    }

    return array_map('strval', array_values($dados));
}

function descrever_voos(int $total): string
{
    return $total === 1 ? '1 voo' : $total . ' voos';
}

function renderizar_modal_exclusao(string $titulo = 'Confirmar exclusão', string $acao = 'Excluir', string $voltar = 'Cancelar'): void
{
    ?>
<div id="delete-modal" class="modal-assentos" hidden>
  <div class="painel-modal-assentos painel-modal-exclusao">
    <h3 class="titulo-modal-exclusao"> <?= esc($titulo) ?></h3>
    <p id="delete-modal-text" class="texto-modal-exclusao"></p>

    <div class="acoes-modal-exclusao">
      <button type="button" id="delete-modal-cancel" class="botao-pequeno"><?= esc($voltar) ?></button>
      <button type="button" id="delete-modal-confirm" class="botao-pequeno botao-perigo"><?= esc($acao) ?></button>
    </div>
  </div>
</div>
    <?php
}
