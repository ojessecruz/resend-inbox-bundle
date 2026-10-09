<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Controller;

use Jessecruz\ResendInbox\Exceptions\InvalidWebhook;
use Jessecruz\ResendInbox\Webhook\UnhandledEvent;
use Jessecruz\ResendInbox\Webhook\WebhookParser;
use Jessecruz\ResendInboxBundle\Message\ProcessResendWebhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Receives Resend webhooks. The Svix signature is verified against the
 * configured signing secret; the event itself goes to the message bus.
 */
final readonly class WebhookController
{
    public function __construct(
        private MessageBusInterface $bus,
        #[Autowire(param: 'resend_inbox.resend.webhook_secret')]
        private string $secret,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($this->secret === '') {
            throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            $event = (new WebhookParser($this->secret))->parse($request->getContent(), $request->headers->all());
        } catch (InvalidWebhook $e) {
            throw new HttpException(str_contains($e->getMessage(), 'signature') ? Response::HTTP_UNAUTHORIZED : Response::HTTP_BAD_REQUEST, previous: $e);
        }

        if (! $event instanceof UnhandledEvent) {
            $this->bus->dispatch(new ProcessResendWebhook($event));
        }

        return new JsonResponse(['ok' => true]);
    }
}
