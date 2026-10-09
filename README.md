# Resend Inbox Bundle

A shared email inbox for Symfony apps, on top of [Resend inbound email](https://resend.com/docs/dashboard/receiving/introduction). Mail to your addresses (`support@`, `sales@`…) shows up in your app as conversations, and your team answers them from there.

- A tab per address, plus "Others" for any other address on your domain.
- Replies threaded by `In-Reply-To` / `References`, falling back to the same subject from the same person.
- New emails and replies written in Markdown, with a signature per address.
- Delivery status (delivered, bounced, marked as spam) from Resend webhooks.
- Attachments keep only their metadata and are downloaded from Resend on demand.
- Remote images blocked until the reader allows them; email HTML rendered in a sandboxed iframe.
- Events for new emails and failed deliveries, to alert someone.

The decisions (threading, replies, auto-reply detection, webhook parsing) live in the framework-agnostic core, [`ojessecruz/resend-inbox`](https://github.com/ojessecruz/resend-inbox). For Laravel, see [`ojessecruz/laravel-resend-inbox`](https://github.com/ojessecruz/laravel-resend-inbox).

## Requirements

- PHP 8.3+ (8.4+ for Symfony 8)
- Symfony 7.4 or 8
- Doctrine ORM 3.4+ (SQLite, MySQL or PostgreSQL)
- A Resend account with receiving enabled on your domain, and an API key with full access

## Installation

```bash
composer require ojessecruz/resend-inbox-bundle
```

Without Symfony Flex, register the bundle in `config/bundles.php`:

```php
Jessecruz\ResendInboxBundle\ResendInboxBundle::class => ['all' => true],
```

Configure it in `config/packages/resend_inbox.yaml`:

```yaml
resend_inbox:
    mailboxes: ['support@example.com', 'sales@example.com']
    sender_name: 'Acme'
    access_role: ROLE_ADMIN
    resend:
        api_key: '%env(RESEND_API_KEY)%'
        webhook_secret: '%env(RESEND_WEBHOOK_SECRET)%'
```

| Key | |
|---|---|
| `mailboxes` | Addresses shown as tabs and allowed to send a new email; the first is the default sender. Mail to any other address on the domain goes to "Others". |
| `domain` | Domain whose inbound mail belongs in the inbox. Defaults to the domain of the first mailbox. Mail addressed only to other domains on the same Resend account is dropped. |
| `sender_name` | Display name on every email sent from the inbox. |
| `signatures` | Markdown signature per address: `{ 'support@example.com': "Jane\nAcme Support" }`. |
| `access_role` | Role allowed to open the inbox. Required. |
| `per_page` | Conversations per page (25). |
| `resend.api_key` | A Resend API key with full access: it sends email and reads received emails. |
| `resend.webhook_secret` | The signing secret (`whsec_…`) of the webhook below. While it is empty, the webhook route answers 503. |

Import the routes in `config/routes/resend_inbox.yaml`. The screens go under a prefix behind your firewall; the webhook stays public (it is verified by its signature):

```yaml
resend_inbox:
    resource: '@ResendInboxBundle/config/routes/inbox.php'
    prefix: /admin/inbox

resend_inbox_webhook:
    resource: '@ResendInboxBundle/config/routes/webhook.php'
```

Create the tables (`inbox_threads` and `inbox_messages`) and install the assets:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console assets:install
```

The bundle adds its entities to the default entity manager. If your app configures several entity managers, map `Jessecruz\ResendInboxBundle\Entity` (attributes, in `vendor/ojessecruz/resend-inbox-bundle/src/Entity`) on one of them yourself.

### Resend

1. Enable receiving on your domain in Resend and point its MX record to Resend.
2. Create a webhook to `https://your-app.com/resend/inbox-webhook` with the events `email.received`, `email.sent`, `email.delivered`, `email.bounced` and `email.complained`.
3. Put its signing secret in `RESEND_WEBHOOK_SECRET`. Resend generates it; it can't be chosen.

Before switching the MX record, you can test with the `@<id>.resend.app` address Resend gives your account: mail to it lands in "Others".

### Processing webhooks in the background

Webhooks are handled through Symfony Messenger. Without routing, they are processed during the request. To process them on a worker, route the message to an async transport:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Jessecruz\ResendInboxBundle\Message\ProcessResendWebhook: async
```

Every step is idempotent, so Resend redeliveries and transport retries are safe.

## Who can open the inbox

Every screen checks the `RESEND_INBOX_VIEW` attribute. The bundle's voter grants it to users with `access_role` (role hierarchy included); nobody else gets in. For other rules, add your own voter for the attribute, and use `is_granted('RESEND_INBOX_VIEW')` to show the inbox in your menu.

Protect the prefix in `security.yaml` as well, so guests are sent to your login page:

```yaml
security:
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

## Getting notified

Listen to the events to alert someone:

```php
use Jessecruz\ResendInboxBundle\Event\InboxEmailReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class NotifyTeamOfNewEmail
{
    public function __invoke(InboxEmailReceived $event): void
    {
        $message = $event->message;

        // $message->fromLabel(), $message->getSubject(), $message->getMailbox(),
        // $message->getThread()->getId() for a link to resend_inbox_thread
    }
}
```

| Event | When |
|---|---|
| `InboxEmailReceived` | A person wrote to one of your addresses. Auto-replies and bounces are stored but don't dispatch it. Dispatched once per email. |
| `InboxEmailDeliveryFailed` | An email sent from the inbox bounced or was marked as spam. Dispatched once. |

The unread counter for your menu is available in Twig: `{{ resend_inbox_unread_count() }}`.

## Layout and styles

The screens render as a standalone page by default. To show them inside your layout, override `templates/bundles/ResendInboxBundle/layout.html.twig` and keep the `resend_inbox_content` block:

```twig
{% extends 'base.html.twig' %}

{% block stylesheets %}
    {{ parent() }}
    {{ include('@ResendInbox/_assets.html.twig') }}
{% endblock %}

{% block body %}
    {% block resend_inbox_content %}{% endblock %}
{% endblock %}
```

The look comes from `inbox.css`, driven by CSS variables. Override them to match your app:

```css
.inbox {
    --inbox-accent: #4f46e5;
    --inbox-font: "Inter", sans-serif;
    --inbox-radius: 6px;
}
```

It follows the system's dark mode. `inbox.js` only adds conveniences (select all, autosizing the email iframe, applying the status filter on change); every screen works without it.

## Customizing the screens

The screens are made of small templates. Override only the one you need, at the same path under `templates/bundles/ResendInboxBundle/`:

| Template | |
|---|---|
| `layout.html.twig` | The page around every screen. |
| `_assets.html.twig` | The stylesheet and script tags. |
| `inbox/index.html.twig` | The conversation list. |
| `inbox/show.html.twig` | A conversation and its reply box. |
| `inbox/compose.html.twig` | A new email. |
| `partials/_heading.html.twig` | Page title and actions. |
| `partials/_tabs.html.twig` | The tabs with unread counters. |
| `partials/_thread_row.html.twig` | One conversation in the list. |
| `partials/_message.html.twig` | One email in a conversation. |
| `partials/_email_body.html.twig` | The sandboxed iframe with the email's HTML. |
| `partials/_delivery_status.html.twig` | The delivery badge. |
| `partials/_email_form.html.twig` | The From / To / Subject / Message form. |
| `partials/_flashes.html.twig` | Notices after an action. |
| `partials/_pagination.html.twig` | Previous / next. |

Template names, their blocks and the variables they receive are the bundle's public API: breaking changes to them only happen in major versions. An overridden template stops receiving the bundle's updates, so prefer overriding the small partials over whole screens.

## Translations

English and Brazilian Portuguese are included, in the `resend_inbox` domain (validation messages in `validators`). Override any key in your app's `translations/resend_inbox.<locale>.*` file.

## Testing

```bash
composer test
composer analyse
```

The suite runs on SQLite in memory. Set `DATABASE_URL` to run it on MySQL or PostgreSQL.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening an issue or a pull request. Security problems are reported privately: see [SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
