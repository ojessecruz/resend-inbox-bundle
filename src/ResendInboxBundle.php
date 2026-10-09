<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * A shared email inbox on top of Resend inbound email: conversations per
 * address, replies threaded by Message-ID, delivery status from webhooks.
 */
final class ResendInboxBundle extends AbstractBundle
{
    protected string $extensionAlias = 'resend_inbox';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('domain')
                    ->info('Domain whose inbound mail belongs in the inbox. Defaults to the domain of the first mailbox.')
                    ->defaultNull()
                ->end()
                ->arrayNode('mailboxes')
                    ->info('Addresses shown as tabs and allowed to send a new email; the first is the default sender. Mail to any other address on the domain goes to the "Others" tab.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('sender_name')
                    ->info('Display name on every email sent from the inbox.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('signatures')
                    ->info('Markdown signature per address, appended below the message.')
                    ->useAttributeAsKey('address')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('access_role')
                    ->info('Role allowed to open the inbox (checked through the RESEND_INBOX_VIEW attribute).')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('per_page')
                    ->min(1)
                    ->defaultValue(25)
                ->end()
                ->arrayNode('resend')
                    ->isRequired()
                    ->children()
                        ->scalarNode('api_key')
                            ->info('A Resend API key with full access (sending and reading received emails).')
                            ->isRequired()
                        ->end()
                        ->scalarNode('webhook_secret')
                            ->info('Signing secret (whsec_...) of the Resend webhook posting to the webhook route. While empty the route answers 503.')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param  array{domain: ?string, mailboxes: list<string>, sender_name: string, signatures: array<string, string>, access_role: string, per_page: int, resend: array{api_key: string, webhook_secret: string}}  $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('resend_inbox.domain', $config['domain'])
            ->set('resend_inbox.mailboxes', $config['mailboxes'])
            ->set('resend_inbox.sender_name', $config['sender_name'])
            ->set('resend_inbox.signatures', $config['signatures'])
            ->set('resend_inbox.access_role', $config['access_role'])
            ->set('resend_inbox.per_page', $config['per_page'])
            ->set('resend_inbox.resend.api_key', $config['resend']['api_key'])
            ->set('resend_inbox.resend.webhook_secret', $config['resend']['webhook_secret']);

        $container->import('../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'ResendInboxBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $this->getPath().'/src/Entity',
                        'prefix' => 'Jessecruz\\ResendInboxBundle\\Entity',
                        'alias' => 'ResendInbox',
                    ],
                ],
            ],
        ]);
    }
}
