<?php

/**
 * Aqui a gente bloqueia o acesso direto aos arquivos internos desta pasta.
 */

// Sem acesso direto, a gente responde com erro 403.
http_response_code(403);
exit('Acesso não permitido.');
