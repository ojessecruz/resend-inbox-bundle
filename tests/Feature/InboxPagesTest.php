<?php

declare(strict_types=1);

use Jessecruz\ResendInboxBundle\Action\StoreReceivedEmail;
use Jessecruz\ResendInboxBundle\Entity\InboxMessage;
use Jessecruz\ResendInboxBundle\Entity\InboxThread;

function storedEmail(array $overrides = []): InboxMessage
{
    return test()->service(StoreReceivedEmail::class)->handle(test()->receive($overrides)->resendId);
}

function threadOf(InboxMessage $message): InboxThread
{
    test()->entityManager()->clear();

    return test()->entityManager()->find(InboxThread::class, $message->getThread()->getId());
}

it('asks guests to log in', function () {
    $this->client->request('GET', '/inbox/');

    expect($this->client->getResponse()->getStatusCode())->toBe(401);
});

it('forbids users without the configured role, on every screen', function (string $method, string $uri) {
    $message = storedEmail();
    $this->actingAsMember()->request($method, str_replace('{id}', (string) $message->getThread()->getId(), $uri));

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
})->with([
    'list' => ['GET', '/inbox/'],
    'conversation' => ['GET', '/inbox/{id}'],
    'compose' => ['GET', '/inbox/compose'],
    'archive' => ['POST', '/inbox/{id}/archive'],
    'bulk' => ['POST', '/inbox/bulk'],
]);

it('lists conversations with a tab per mailbox, Others and unread counters', function () {
    storedEmail(['subject' => 'Support question']);
    storedEmail(['subject' => 'Billing question', 'to' => ['billing@example.com'], 'fromAddress' => 'joe@customer.test']);

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/');

    expect($this->client->getResponse()->isSuccessful())->toBeTrue()
        ->and($crawler->filter('.inbox-tab')->each(fn ($tab) => trim(preg_replace('/\s+/', ' ', $tab->text()))))->toBe(['All 2', 'support@example.com 1', 'sales@example.com', 'Others 1'])
        ->and($crawler->filter('.inbox-row')->count())->toBe(2);

    $others = $this->client->request('GET', '/inbox/?tab=others');

    expect($others->filter('.inbox-row__subject')->each(fn ($row) => $row->text()))->toBe(['Billing question']);
});

it('searches by subject and sender, and paginates', function () {
    storedEmail(['subject' => 'Refund please', 'fromAddress' => 'ana@customer.test']);
    storedEmail(['subject' => 'Hello', 'fromAddress' => 'bob@customer.test']);
    storedEmail(['subject' => 'Another hello', 'fromAddress' => 'carl@customer.test']);

    $bySubject = $this->actingAsAdmin()->request('GET', '/inbox/?search=refund');
    expect($bySubject->filter('.inbox-row')->count())->toBe(1);

    $bySender = $this->client->request('GET', '/inbox/?search=bob%40');
    expect($bySender->filter('.inbox-row__subject')->text())->toBe('Hello');

    $firstPage = $this->client->request('GET', '/inbox/');
    expect($firstPage->filter('.inbox-row')->count())->toBe(2)
        ->and($firstPage->filter('.inbox-pagination a[rel=next]')->count())->toBe(1);
});

it('shows a conversation, marks it read and prefills the reply', function () {
    $message = storedEmail(['subject' => 'Pricing', 'replyTo' => ['billing@customer.test']]);

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/'.$message->getThread()->getId());

    expect($this->client->getResponse()->isSuccessful())->toBeTrue()
        ->and($crawler->filter('.inbox-heading__title')->text())->toBe('Pricing')
        ->and($crawler->filter('.inbox-message')->count())->toBe(1)
        ->and($crawler->filter('#resend_inbox_email_to')->attr('value'))->toBe('billing@customer.test')
        ->and($crawler->filter('#resend_inbox_email_subject')->attr('value'))->toBe('Re: Pricing')
        ->and(threadOf($message)->isUnread())->toBeFalse();
});

it('blocks remote images until they are allowed', function () {
    $message = storedEmail(['html' => '<p>Hi</p><img src="https://tracker.test/pixel.gif">']);
    $id = $message->getThread()->getId();

    $blocked = $this->actingAsAdmin()->request('GET', "/inbox/{$id}");
    expect($blocked->filter('.inbox-message__images')->count())->toBe(1)
        ->and($blocked->filter('iframe')->attr('srcdoc'))->toContain('img-src data:;');

    $allowed = $this->client->request('GET', "/inbox/{$id}?images=".$message->getId());
    expect($allowed->filter('.inbox-message__images')->count())->toBe(0)
        ->and($allowed->filter('iframe')->attr('srcdoc'))->toContain('img-src https: http: data:');
});

it('sends a reply from the conversation', function () {
    $message = storedEmail(['subject' => 'Pricing']);
    $id = $message->getThread()->getId();

    $crawler = $this->actingAsAdmin()->request('GET', "/inbox/{$id}");
    $this->client->submit($crawler->filter('form.inbox-form')->form(), ['resend_inbox_email[body]' => 'It is free.']);

    expect($this->client->getResponse()->isRedirect("/inbox/{$id}"))->toBeTrue()
        ->and($this->resend()->sentEmails())->toHaveCount(1)
        ->and(threadOf($message)->getMessages())->toHaveCount(2);

    $this->client->followRedirect();
    expect($this->client->getCrawler()->filter('.inbox-notice')->text())->toBe('Reply sent.');
});

it('shows validation errors instead of sending', function () {
    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/compose');
    $this->client->submit($crawler->filter('form.inbox-form')->form(), [
        'resend_inbox_email[to]' => 'maria@customer.test, not-an-address',
        'resend_inbox_email[subject]' => 'Hello',
        'resend_inbox_email[body]' => 'Hi',
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getCrawler()->filter('.inbox-field__error')->text())->toBe('not-an-address is not a valid email address.')
        ->and($this->resend()->sentEmails())->toBe([]);
});

it('composes a new email and opens its conversation', function () {
    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/compose');
    $this->client->submit($crawler->filter('form.inbox-form')->form(), [
        'resend_inbox_email[from]' => 'sales@example.com',
        'resend_inbox_email[to]' => 'maria@customer.test',
        'resend_inbox_email[subject]' => 'Proposal',
        'resend_inbox_email[body]' => 'Here it is.',
    ]);

    $thread = $this->entityManager()->getRepository(InboxThread::class)->findOneBy(['subject' => 'Proposal']);

    expect($thread?->getMailbox())->toBe('sales@example.com')
        ->and($this->client->getResponse()->isRedirect('/inbox/'.$thread?->getId()))->toBeTrue();
});

it('shows an error on the form when Resend fails', function () {
    $this->resend()->failSends = true;

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/compose');
    $this->client->submit($crawler->filter('form.inbox-form')->form(), [
        'resend_inbox_email[to]' => 'maria@customer.test',
        'resend_inbox_email[subject]' => 'Proposal',
        'resend_inbox_email[body]' => 'Here it is.',
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getCrawler()->filter('.inbox-field__error')->text())->toBe('Resend could not send the email. Try again in a moment.');
});

it('archives, unarchives and marks a conversation as unread', function () {
    $message = storedEmail();
    $id = $message->getThread()->getId();

    $crawler = $this->actingAsAdmin()->request('GET', "/inbox/{$id}");
    $this->client->submit($crawler->selectButton('Archive')->form());
    expect($this->client->getResponse()->isRedirect('/inbox/'))->toBeTrue()
        ->and(threadOf($message)->isArchived())->toBeTrue();

    $crawler = $this->client->request('GET', "/inbox/{$id}");
    $this->client->submit($crawler->selectButton('Move to inbox')->form());
    expect(threadOf($message)->isArchived())->toBeFalse();

    $crawler = $this->client->request('GET', "/inbox/{$id}");
    $this->client->submit($crawler->selectButton('Mark as unread')->form());
    expect(threadOf($message)->isUnread())->toBeTrue();
});

it('marks the ticked conversations as read in bulk', function () {
    $first = storedEmail(['subject' => 'One']);
    $second = storedEmail(['subject' => 'Two', 'fromAddress' => 'other@customer.test']);
    $third = storedEmail(['subject' => 'Three', 'fromAddress' => 'third@customer.test']);

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/');
    $form = $crawler->selectButton('Mark as read')->form();
    $form['ids'][0]->tick();
    $form['ids'][1]->tick();
    $this->client->submit($form);

    $unread = array_filter([threadOf($first), threadOf($second), threadOf($third)], fn ($thread) => $thread->isUnread());
    expect($unread)->toHaveCount(1);

    $this->client->followRedirect();
    expect($this->client->getCrawler()->filter('.inbox-notice')->text())->toBe('2 conversations marked as read.');
});

it('marks the ticked conversations as unread in bulk', function () {
    $first = storedEmail(['subject' => 'One']);
    $second = storedEmail(['subject' => 'Two', 'fromAddress' => 'other@customer.test']);

    foreach ([$first, $second] as $message) {
        threadOf($message)->markRead();
        $this->entityManager()->flush();
    }

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/');
    $form = $crawler->selectButton('Mark as unread')->form();
    $form['ids'][0]->tick();
    $this->client->submit($form);

    $unread = array_filter([threadOf($first), threadOf($second)], fn ($thread) => $thread->isUnread());
    expect($unread)->toHaveCount(1);

    $this->client->followRedirect();
    expect($this->client->getCrawler()->filter('.inbox-notice')->text())->toBe('1 conversation marked as unread.');
});

it('archives the ticked conversations in bulk', function () {
    $first = storedEmail(['subject' => 'One']);
    $second = storedEmail(['subject' => 'Two', 'fromAddress' => 'other@customer.test']);

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/');
    $form = $crawler->selectButton('Archive')->form();
    $form['ids'][0]->tick();
    $form['ids'][1]->tick();
    $this->client->submit($form);

    expect(threadOf($first)->isArchived())->toBeTrue()
        ->and(threadOf($second)->isArchived())->toBeTrue();

    $this->client->followRedirect();
    expect($this->client->getCrawler()->filter('.inbox-notice')->text())->toBe('2 conversations archived.');
});

it('rejects actions without a valid CSRF token', function () {
    $message = storedEmail();

    $this->actingAsAdmin()->request('POST', '/inbox/'.$message->getThread()->getId().'/archive', ['_token' => 'forged']);

    expect($this->client->getResponse()->getStatusCode())->toBe(403)
        ->and(threadOf($message)->isArchived())->toBeFalse();
});

it('redirects to the Resend download URL of an attachment it knows', function () {
    $message = storedEmail(['attachments' => [['id' => 'att_1', 'filename' => 'invoice.pdf', 'size' => 2048]]]);
    $this->resend()->attachment((string) $message->getResendId(), 'att_1', 'https://resend.test/download/att_1');

    $crawler = $this->actingAsAdmin()->request('GET', '/inbox/'.$message->getThread()->getId());
    expect($crawler->filter('.inbox-message__attachments a')->text())->toContain('invoice.pdf')->toContain('2.0 KB');

    $this->client->request('GET', '/inbox/messages/'.$message->getId().'/attachments/att_1');
    expect($this->client->getResponse()->isRedirect('https://resend.test/download/att_1'))->toBeTrue();

    $this->client->request('GET', '/inbox/messages/'.$message->getId().'/attachments/att_2');
    expect($this->client->getResponse()->getStatusCode())->toBe(404);
});

it('exposes the unread count to Twig for the app menu', function () {
    storedEmail();
    storedEmail(['subject' => 'Other', 'fromAddress' => 'x@customer.test']);

    $twig = static::getContainer()->get('twig');

    expect($twig->createTemplate('{{ resend_inbox_unread_count() }}')->render())->toBe('2');
});
