<?php

namespace AppBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use AppBundle\Security\TeamVoter;
use AppBundle\Entity\Team;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TeamVoterTest extends TestCase
{
    public function testOwnerCanEditAndDelete()
    {
        $user = new User();
        $user->setUsername('owner');
        $user->setEmail('owner@example.com');

        $team = new Team();
        $team->setName('My Team');
        $team->setOwner($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $voter = new TeamVoter();
        $this->assertTrue($voter->voteOnAttribute(TeamVoter::EDIT, $team, $token));
        $this->assertTrue($voter->voteOnAttribute(TeamVoter::DELETE, $team, $token));
    }

    public function testAnonymousCanViewPublic()
    {
        $team = new Team();
        $team->setName('Public');
        $team->setVisibility('public');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $voter = new TeamVoter();
        $this->assertTrue($voter->voteOnAttribute(TeamVoter::VIEW, $team, $token));
    }

    public function testNonOwnerCannotEdit()
    {
        $owner = new User();
        $owner->setUsername('owner');
        $owner->setEmail('owner@example.com');

        $other = new User();
        $other->setUsername('other');
        $other->setEmail('other@example.com');

        $team = new Team();
        $team->setName('Private');
        $team->setOwner($owner);
        $team->setVisibility('private');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($other);

        $voter = new TeamVoter();
        $this->assertFalse($voter->voteOnAttribute(TeamVoter::EDIT, $team, $token));
        $this->assertFalse($voter->voteOnAttribute(TeamVoter::DELETE, $team, $token));
    }
}
