<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jessecruz\ResendInboxBundle\ResendInboxBundle;
use Jessecruz\ResendInboxBundle\Tests\Support\FakeResend;
use Jessecruz\ResendInboxBundle\Tests\Support\RecordedEvents;
use Psr\Log\NullLogger;
use Resend\Client;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * A minimal app with the bundle installed: SQLite in memory (or DATABASE_URL),
 * two in-memory users and the Resend API replaced by FakeResend.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public const string WEBHOOK_SECRET = 'whsec_dGVzdC13ZWJob29rLXNpZ25pbmctc2VjcmV0';

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle;
        yield new SecurityBundle;
        yield new TwigBundle;
        yield new DoctrineBundle;
        yield new ResendInboxBundle;
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return dirname(__DIR__, 2).'/build/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return dirname(__DIR__, 2).'/build/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'form' => ['csrf_protection' => true],
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
            'validation' => true,
            'router' => ['utf8' => true],
            'messenger' => null,
            'assets' => null,
        ]);

        $container->extension('security', [
            'password_hashers' => ['Symfony\Component\Security\Core\User\InMemoryUser' => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'admin@example.com' => ['password' => 'secret', 'roles' => ['ROLE_ADMIN']],
                'member@example.com' => ['password' => 'secret', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users', 'http_basic' => null]],
            'access_control' => [['path' => '^/inbox', 'roles' => 'IS_AUTHENTICATED_FULLY']],
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        $container->extension('doctrine', [
            'dbal' => ['url' => $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? 'sqlite:///:memory:'],
            'orm' => [],
        ]);

        $container->extension('resend_inbox', [
            'domain' => 'example.com',
            'mailboxes' => ['support@example.com', 'sales@example.com'],
            'sender_name' => 'Acme',
            'signatures' => ['support@example.com' => "Jane\nAcme Support"],
            'access_role' => 'ROLE_ADMIN',
            'per_page' => 2,
            'resend' => ['api_key' => 're_test', 'webhook_secret' => self::WEBHOOK_SECRET],
        ]);

        $services = $container->services();

        $services->set('logger', NullLogger::class);
        $services->set(FakeResend::class)->public();
        $services->set('resend_inbox.resend_client', Client::class)->args([service(FakeResend::class)]);

        $services->set(RecordedEvents::class)->public()
            ->tag('kernel.event_listener', ['event' => 'Jessecruz\ResendInboxBundle\Event\InboxEmailReceived', 'method' => 'record'])
            ->tag('kernel.event_listener', ['event' => 'Jessecruz\ResendInboxBundle\Event\InboxEmailDeliveryFailed', 'method' => 'record']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@ResendInboxBundle/config/routes/inbox.php')->prefix('/inbox');
        $routes->import('@ResendInboxBundle/config/routes/webhook.php');
    }
}
