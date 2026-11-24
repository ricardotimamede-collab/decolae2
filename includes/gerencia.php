<?php

declare(strict_types=1);

/**
 * Aqui a gente lê um campo obrigatório enviado pelo formulário.
 * 
 * @param string $nomeCampo Nome do campo a recuperar
 * @return string Valor do campo trimado
 * @throws InvalidArgumentException Se o campo estiver vazio
 */
function obter_campo_obrigatorio(string $nomeCampo): string
{
    // A gente remove espaços extras antes de conferir o valor.
    $valor = trim((string)($_POST[$nomeCampo] ?? ''));
    
    // Sem valor, avisamos que o campo precisa ser preenchido.
    if ($valor === '') {
        throw new InvalidArgumentException('Preencha todos os campos.');
    }
    
    return $valor;
}

function descrever_voos(int $total): string
{
    return $total === 1 ? '1 voo' : $total . ' voos';
}

function renderizar_modal_exclusao(): void
{
    ?>
<div id="delete-modal" class="modal-assentos" hidden>
  <div class="painel-modal-assentos painel-modal-exclusao">
    <h3 class="titulo-modal-exclusao">Confirmar exclusão</h3>
    <p id="delete-modal-text" class="texto-modal-exclusao"></p>

    <div class="acoes-modal-exclusao">
      <button type="button" id="delete-modal-cancel" class="botao-pequeno">Cancelar</button>
      <button type="button" id="delete-modal-confirm" class="botao-pequeno botao-perigo">Excluir</button>
    </div>
  </div>
</div>
    <?php
}
