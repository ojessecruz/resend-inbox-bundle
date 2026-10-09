<?php

declare(strict_types=1);

return [
    'titles' => [
        'inbox' => 'Caixa de entrada',
        'compose' => 'Novo e-mail',
        'reply' => 'Responder',
    ],
    'tabs' => [
        'label' => 'Caixas',
        'all' => 'Todas',
        'others' => 'Outros',
    ],
    'status' => [
        'inbox' => 'Caixa de entrada',
        'unread' => 'Não lidas',
        'archived' => 'Arquivadas',
    ],
    'list' => [
        'search' => 'Buscar por assunto ou remetente',
        'status' => 'Situação',
        'empty' => 'Nenhuma conversa aqui.',
        'to' => 'Para: %address%',
    ],
    'actions' => [
        'compose' => 'Novo e-mail',
        'send' => 'Enviar',
        'send_reply' => 'Enviar resposta',
        'archive' => 'Arquivar',
        'unarchive' => 'Mover para a caixa de entrada',
        'mark_read' => 'Marcar como lida',
        'mark_unread' => 'Marcar como não lida',
        'load_images' => 'Carregar imagens',
        'filter' => 'Filtrar',
        'select' => 'Selecionar conversa',
        'select_page' => 'Selecionar todas as conversas desta página',
    ],
    'notices' => [
        'marked_read' => '%count% conversa marcada como lida.|%count% conversas marcadas como lidas.',
        'marked_unread' => '%count% conversa marcada como não lida.|%count% conversas marcadas como não lidas.',
        'archived' => '%count% conversa arquivada.|%count% conversas arquivadas.',
        'unarchived' => '%count% conversa movida para a caixa de entrada.|%count% conversas movidas para a caixa de entrada.',
        'reply_sent' => 'Resposta enviada.',
    ],
    'message' => [
        'to' => 'Para: %address%',
        'cc' => 'Cc: %address%',
        'sent_by' => 'Enviado por %name%',
        'body' => 'Corpo do e-mail',
        'attachment' => 'Anexo',
        'images_blocked' => 'As imagens externas estão bloqueadas.',
    ],
    'fields' => [
        'from' => 'De',
        'to' => 'Para',
        'to_hint' => 'Separe vários endereços com vírgula.',
        'subject' => 'Assunto',
        'body' => 'Mensagem',
        'body_hint' => 'Aceita Markdown.',
    ],
    'validation' => [
        'sender' => 'Escolha um dos endereços de envio permitidos.',
        'send_failed' => 'O Resend não conseguiu enviar o e-mail. Tente de novo em instantes.',
    ],
    'delivery' => [
        'sent' => 'Enviado',
        'delivered' => 'Entregue',
        'bounced' => 'Devolvido',
        'complained' => 'Marcado como spam',
    ],
    'pagination' => [
        'label' => 'Páginas',
        'previous' => 'Anterior',
        'next' => 'Próxima',
        'page' => 'Página %page% de %pages%',
    ],
    'no_subject' => '(sem assunto)',
    'attachment_unavailable' => 'Não foi possível buscar o anexo no Resend.',
    'date_format' => 'd/m/Y H:i',
];
