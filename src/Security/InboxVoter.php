<?php

declare(strict_types=1);

namespace Jessecruz\ResendInboxBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants RESEND_INBOX_VIEW to users with the configured role (role hierarchy
 * included). Nobody else gets in unless the app adds its own voter for the
 * attribute.
 *
 * @extends Voter<string, mixed>
 */
final class InboxVoter extends Voter
{
    public const string VIEW = 'RESEND_INBOX_VIEW';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly string $role,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() !== null && $this->accessDecisionManager->decide($token, [$this->role]);
    }
}
