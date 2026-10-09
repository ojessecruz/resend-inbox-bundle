<?php

declare(strict_types=1);

use Jessecruz\ResendInboxBundle\Controller\WebhookController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The Resend webhook. Public: requests are verified by their signature.
 * Import it outside the inbox prefix and keep it out of your firewall's
 * access_control:
 *
 *   resend_inbox_webhook:
 *       resource: '@ResendInboxBundle/config/routes/webhook.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('resend_inbox_webhook', '/resend/inbox-webhook')
        ->controller(WebhookController::class)
        ->methods(['POST']);
};
