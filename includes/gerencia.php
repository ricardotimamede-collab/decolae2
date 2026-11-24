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
