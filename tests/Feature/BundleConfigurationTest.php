<?php

declare(strict_types=1);

use Jessecruz\ResendInboxBundle\ResendInboxBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

function loadInboxConfig(array $config): ContainerBuilder
{
    $container = new ContainerBuilder;
    $container->setParameter('kernel.environment', 'test');
    $container->setParameter('kernel.build_dir', sys_get_temp_dir());
    $extension = (new ResendInboxBundle)->getContainerExtension();
    $extension->load([$config], $container);

    return $container;
}

it('requires the role allowed to open the inbox', function () {
    loadInboxConfig(['sender_name' => 'Acme', 'resend' => ['api_key' => 're_x']]);
})->throws(InvalidConfigurationException::class, 'access_role');

it('requires a sender name', function () {
    loadInboxConfig(['access_role' => 'ROLE_ADMIN', 'resend' => ['api_key' => 're_x']]);
})->throws(InvalidConfigurationException::class, 'sender_name');

it('maps the configuration to parameters', function () {
    $container = loadInboxConfig([
        'mailboxes' => ['support@example.com'],
        'sender_name' => 'Acme',
        'access_role' => 'ROLE_SUPPORT',
        'resend' => ['api_key' => 're_x'],
    ]);

    expect($container->getParameter('resend_inbox.access_role'))->toBe('ROLE_SUPPORT')
        ->and($container->getParameter('resend_inbox.resend.webhook_secret'))->toBe('')
        ->and($container->getParameter('resend_inbox.per_page'))->toBe(25)
        ->and($container->getParameter('resend_inbox.theme'))->toBe('system');
});

it('accepts only the known themes', function () {
    expect(loadInboxConfig(['sender_name' => 'Acme', 'access_role' => 'ROLE_ADMIN', 'theme' => 'app', 'resend' => ['api_key' => 're_x']])->getParameter('resend_inbox.theme'))->toBe('app');

    loadInboxConfig(['sender_name' => 'Acme', 'access_role' => 'ROLE_ADMIN', 'theme' => 'blue', 'resend' => ['api_key' => 're_x']]);
})->throws(InvalidConfigurationException::class, 'theme');
