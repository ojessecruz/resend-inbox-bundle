<?php

declare(strict_types=1);

use Jessecruz\ResendInbox\ResendMailbox;
use Jessecruz\ResendInbox\Threading\MessageLookup;
use Jessecruz\ResendInbox\Threading\ThreadResolver;
use Jessecruz\ResendInboxBundle\Doctrine\DoctrineMessageLookup;
use Jessecruz\ResendInboxBundle\Security\InboxVoter;
use Jessecruz\ResendInboxBundle\Settings\InboxSettings;
use Resend\Client;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->load('Jessecruz\\ResendInboxBundle\\', '../src/')
        ->exclude([
            '../src/Entity/',
            '../src/Event/',
            '../src/Message/',
            '../src/Form/EmailData.php',
            '../src/ResendInboxBundle.php',
        ]);

    $services->load('Jessecruz\\ResendInboxBundle\\Controller\\', '../src/Controller/')
        ->tag('controller.service_arguments');

    $services->set(InboxSettings::class)
        ->args([
            param('resend_inbox.domain'),
            param('resend_inbox.mailboxes'),
            param('resend_inbox.sender_name'),
            param('resend_inbox.signatures'),
            param('resend_inbox.per_page'),
            param('resend_inbox.theme'),
        ]);

    $services->set('resend_inbox.resend_client', Client::class)
        ->factory([Resend::class, 'client'])
        ->args([param('resend_inbox.resend.api_key')]);

    $services->set(ResendMailbox::class)
        ->args([service('resend_inbox.resend_client')]);

    $services->alias(MessageLookup::class, DoctrineMessageLookup::class);

    $services->set(ThreadResolver::class)
        ->args([service(MessageLookup::class)]);

    $services->set(InboxVoter::class)
        ->arg('$role', param('resend_inbox.access_role'));
};
