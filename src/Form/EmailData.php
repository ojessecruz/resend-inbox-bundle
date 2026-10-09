<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Form;

use Jessecruz\ResendInbox\EmailAddress;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Fields shared by the compose page and the reply box. The allowed From
 * addresses are enforced by the SendEmail action as well.
 */
final class EmailData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $from = '';

    /** Comma-separated recipient addresses. */
    #[Assert\NotBlank]
    public string $to = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 998)]
    public string $subject = '';

    /** Markdown body. */
    #[Assert\NotBlank]
    #[Assert\Length(max: 100000)]
    public string $body = '';

    /**
     * @return list<string>
     */
    public function recipients(): array
    {
        return EmailAddress::list($this->to);
    }

    #[Assert\Callback]
    public function validateRecipients(ExecutionContextInterface $context): void
    {
        if (trim($this->to) === '') {
            return;
        }

        $recipients = $this->recipients();

        if ($recipients === []) {
            $context->buildViolation('resend_inbox.recipients')->atPath('to')->addViolation();

            return;
        }

        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                $context->buildViolation('resend_inbox.recipient')
                    ->setParameter('{{ address }}', $recipient)
                    ->atPath('to')
                    ->addViolation();

                return;
            }
        }
    }
}
