<?php

namespace AppBundle\Security;

use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use AppBundle\Entity\Team;

class TeamVoter extends Voter
{
    const VIEW = 'VIEW';
    const EDIT = 'EDIT';
    const DELETE = 'DELETE';

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, array(self::VIEW, self::EDIT, self::DELETE))) {
            return false;
        }

        if (!$subject instanceof Team) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!is_object($user)) {
            // the user must be logged in; anonymous users cannot do more than view public
            if ($attribute === self::VIEW) {
                return $subject->getVisibility() === 'public';
            }
            return false;
        }

        // owners can do anything
        if ($subject->getOwner() && $subject->getOwner()->getId() === $user->getId()) {
            return true;
        }

        switch ($attribute) {
            case self::VIEW:
                return $subject->getVisibility() === 'public';
            case self::EDIT:
            case self::DELETE:
                return false;
        }

        return false;
    }
}
