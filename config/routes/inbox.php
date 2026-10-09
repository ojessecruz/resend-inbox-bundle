<?php

declare(strict_types=1);

use Jessecruz\ResendInboxBundle\Controller\AttachmentController;
use Jessecruz\ResendInboxBundle\Controller\InboxController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The inbox screens. Import with a prefix behind your firewall:
 *
 *   resend_inbox:
 *       resource: '@ResendInboxBundle/config/routes/inbox.php'
 *       prefix: /admin/inbox
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('resend_inbox_index', '/')
        ->controller([InboxController::class, 'index'])
        ->methods(['GET']);

    $routes->add('resend_inbox_compose', '/compose')
        ->controller([InboxController::class, 'compose'])
        ->methods(['GET', 'POST']);

    $routes->add('resend_inbox_bulk', '/bulk')
        ->controller([InboxController::class, 'bulk'])
        ->methods(['POST']);

    $routes->add('resend_inbox_thread', '/{id}')
        ->controller([InboxController::class, 'show'])
        ->requirements(['id' => '\d+'])
        ->methods(['GET', 'POST']);

    $routes->add('resend_inbox_thread_action', '/{id}/{action}')
        ->controller([InboxController::class, 'threadAction'])
        ->requirements(['id' => '\d+', 'action' => 'mark_unread|archive|unarchive'])
        ->methods(['POST']);

    $routes->add('resend_inbox_attachment', '/messages/{id}/attachments/{attachment}')
        ->controller(AttachmentController::class)
        ->requirements(['id' => '\d+'])
        ->methods(['GET']);
};
