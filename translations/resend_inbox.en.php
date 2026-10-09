<?php

declare(strict_types=1);

return [
    'titles' => [
        'inbox' => 'Inbox',
        'compose' => 'New email',
        'reply' => 'Reply',
    ],
    'tabs' => [
        'label' => 'Mailboxes',
        'all' => 'All',
        'others' => 'Others',
    ],
    'status' => [
        'inbox' => 'Inbox',
        'unread' => 'Unread',
        'archived' => 'Archived',
    ],
    'list' => [
        'search' => 'Search subject or sender',
        'status' => 'Status',
        'empty' => 'No conversations here.',
        'to' => 'To: %address%',
    ],
    'actions' => [
        'compose' => 'New email',
        'send' => 'Send',
        'send_reply' => 'Send reply',
        'archive' => 'Archive',
        'unarchive' => 'Move to inbox',
        'mark_read' => 'Mark as read',
        'mark_unread' => 'Mark as unread',
        'load_images' => 'Load images',
        'filter' => 'Filter',
        'select' => 'Select conversation',
        'select_page' => 'Select every conversation on this page',
    ],
    'notices' => [
        'marked_read' => '%count% conversation marked as read.|%count% conversations marked as read.',
        'archived' => '%count% conversation archived.|%count% conversations archived.',
        'unarchived' => '%count% conversation moved to the inbox.|%count% conversations moved to the inbox.',
        'reply_sent' => 'Reply sent.',
    ],
    'message' => [
        'to' => 'To: %address%',
        'cc' => 'Cc: %address%',
        'sent_by' => 'Sent by %name%',
        'body' => 'Email body',
        'attachment' => 'Attachment',
        'images_blocked' => 'Remote images are blocked.',
    ],
    'fields' => [
        'from' => 'From',
        'to' => 'To',
        'to_hint' => 'Separate multiple addresses with commas.',
        'subject' => 'Subject',
        'body' => 'Message',
        'body_hint' => 'Markdown is supported.',
    ],
    'validation' => [
        'sender' => 'Pick one of the allowed sender addresses.',
        'send_failed' => 'Resend could not send the email. Try again in a moment.',
    ],
    'delivery' => [
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'bounced' => 'Bounced',
        'complained' => 'Marked as spam',
    ],
    'pagination' => [
        'label' => 'Pages',
        'previous' => 'Previous',
        'next' => 'Next',
        'page' => 'Page %page% of %pages%',
    ],
    'no_subject' => '(no subject)',
    'attachment_unavailable' => 'The attachment could not be fetched from Resend.',
    'date_format' => 'M j, Y H:i',
];
