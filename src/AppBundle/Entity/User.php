<?php

namespace AppBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;

/**
 * User
 */
class User extends BaseUser
{
	public function getMaxNbDecks()
	{
		return 2*(200+floor($this->reputation/ 10));
	}

    /**
     * @var \DateTime
     */
    private $dateCreation;

    /**
     * @var \DateTime
     */
    private $dateUpdate;

    /**
     * @var integer
     */
    private $reputation;

    /**
     * @var boolean
     */
    private $faq;


    /**
     * @var string
     */
    private $resume;

    /**
     * @var string
     */
    private $color;

    /**
     * @var integer
     */
    private $donation;

    /**
     * @var boolean
     */
    private $isNotifAuthor = true;

    /**
     * @var boolean
     */
    private $isNotifCommenter = true;

    /**
     * @var boolean
     */
    private $isNotifMention = true;

    /**
     * @var boolean
     */
    private $isNotifFollow = true;

    /**
     * @var boolean
     */
    private $isNotifSuccessor = true;

    /**
     * @var boolean
     */
    private $isShareDecks = false;

    /**
     * @var boolean
     */
    private $isNewUI = false;

    /**
     * @var boolean
     */
    private $isAdmin = false;

    /**
     * @var string
     */
    private $ownedPacks;

    /**
     * @var integer
     */
    private $isShareCollection = 0;

    /**
     * @var integer
     */
    private $showIconAspect = 0;

    /**
     * @var integer
     */
    private $showArchetype = 0;

    /**
     * @var string
     */
    private $showTheme;

    /**
     * @var integer
     */
    private $showLegacySchOrder;

    /**
     * @var integer
     */
    private $showTagDefault;

    /**
     * @var integer
     */
    private $printFaction;

    /**
     * @var integer
     */
    private $printType;

    /**
     * @var integer
     */
    private $printTag;

    /**
     * @var integer
     */
    private $printSide;

    /**
     * @var integer
     */
    private $showCurrentOnlyDefault;

    /**
     * @var \DateTime
     */
    private $lastActiveAt;

    /**
     * JSON-encoded visual options (text)
     * @var string
     */
    private $visual_options;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $decks;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $decklists;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $comments;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $reviews;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $favorites;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $votes;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $reviewvotes;

	public function __construct()
	{
		parent::__construct();

		$this->reputation = 1;
		$this->donation = 0;
	}

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     *
     * @return User
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateUpdate
     *
     * @param \DateTime $dateUpdate
     *
     * @return User
     */
    public function setDateUpdate($dateUpdate)
    {
        $this->dateUpdate = $dateUpdate;

        return $this;
    }

    /**
     * Get dateUpdate
     *
     * @return \DateTime
     */
    public function getDateUpdate()
    {
        return $this->dateUpdate;
    }

    /**
     * Set reputation
     *
     * @param integer $reputation
     *
     * @return User
     */
    public function setReputation($reputation)
    {
        $this->reputation = $reputation;

        return $this;
    }

    /**
     * Get reputation
     *
     * @return integer
     */
    public function getReputation()
    {
        return $this->reputation;
    }


    /**
     * Set faq
     *
     * @param boolean $faq
     *
     * @return User
     */
    public function setFaq($faq)
    {
        $this->faq = $faq;

        return $this;
    }

    /**
     * Get faq
     *
     * @return boolean
     */
    public function getFaq()
    {
        return $this->faq;
    }

    /**
     * Set resume
     *
     * @param string $resume
     *
     * @return User
     */
    public function setResume($resume)
    {
        $this->resume = $resume;

        return $this;
    }

    /**
     * Get resume
     *
     * @return string
     */
    public function getResume()
    {
        return $this->resume;
    }

    /**
     * Set color
     *
     * @param string $color
     *
     * @return User
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Get color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set donation
     *
     * @param integer $donation
     *
     * @return User
     */
    public function setDonation($donation)
    {
        $this->donation = $donation;

        return $this;
    }

    /**
     * Get donation
     *
     * @return integer
     */
    public function getDonation()
    {
        return $this->donation;
    }

    /**
     * Set ownedPacks
     *
     * @param string $ownedPacks
     *
     * @return User
     */
    public function setOwnedPacks($ownedPacks) {
        $this->ownedPacks = $ownedPacks;
        return $this;
    }
    /**
     * Get ownedPacks
     *
     * @return string
     */
    public function getOwnedPacks() {
        return $this->ownedPacks;
    }

    /**
     * Set isShareCollection
     *
     * @param integer $isShareCollection
     *
     * @return User
     */
    public function setIsShareCollection($isShareCollection)
    {
        $this->isShareCollection = (int)$isShareCollection;

        return $this;
    }

    /**
     * Get isShareCollection
     *
     * @return integer
     */
    public function getIsShareCollection()
    {
        return $this->isShareCollection;
    }

    /**
     * Set showIconAspect
     *
     * @param integer $showIconAspect
     *
     * @return User
     */
    public function setShowIconAspect($showIconAspect)
    {
        $this->showIconAspect = (int)$showIconAspect;

        return $this;
    }

    /**
     * Get showIconAspect
     *
     * @return integer
     */
    public function getShowIconAspect()
    {
        return $this->showIconAspect;
    }

    /**
     * Set showArchetype
     *
     * @param integer $showArchetype
     *
     * @return User
     */
    public function setShowArchetype($showArchetype)
    {
        $this->showArchetype = (int)$showArchetype;

        return $this;
    }

    /**
     * Get showArchetype
     *
     * @return integer
     */
    public function getShowArchetype()
    {
        return $this->showArchetype;
    }

    /**
     * Set showTheme
     *
     * @param string $showTheme (JSON encoded array of {Theme,boolean} pairs)
     *
     * @return User
     */
    public function setShowTheme($showTheme)
    {
        $this->showTheme = $showTheme;

        return $this;
    }

    /**
     * Get showTheme
     *
     * @return string
     */
    public function getShowTheme()
    {
        return $this->showTheme;
    }

    /**
     * Set showLegacySchOrder
     *
     * @param integer $showLegacySchOrder
     *
     * @return User
     */
    public function setShowLegacySchOrder($showLegacySchOrder)
    {
        $this->showLegacySchOrder = $showLegacySchOrder;

        return $this;
    }

    /**
     * Get showLegacySchOrder
     *
     * @return integer
     */
    public function getShowLegacySchOrder()
    {
        return $this->showLegacySchOrder;
    }

    /**
     * Set showTagDefault
     *
     * @param integer $showTagDefault
     *
     * @return User
     */
    public function setShowTagDefault($showTagDefault)
    {
        $this->showTagDefault = $showTagDefault;

        return $this;
    }

    /**
     * Get showTagDefault
     *
     * @return integer
     */
    public function getShowTagDefault()
    {
        return $this->showTagDefault;
    }

    /**
     * Set printFaction
     *
     * @param integer $printFaction
     *
     * @return User
     */
    public function setPrintFaction($printFaction)
    {
        $this->printFaction = $printFaction;

        return $this;
    }

    /**
     * Get printFaction
     *
     * @return integer
     */
    public function getPrintFaction()
    {
        return $this->printFaction;
    }

    /**
     * Set printType
     *
     * @param integer $printType
     *
     * @return User
     */
    public function setPrintType($printType)
    {
        $this->printType = $printType;

        return $this;
    }

    /**
     * Get printType
     *
     * @return integer
     */
    public function getPrintType()
    {
        return $this->printType;
    }

    /**
     * Set printTag
     *
     * @param integer $printTag
     *
     * @return User
     */
    public function setPrintTag($printTag)
    {
        $this->printTag = $printTag;

        return $this;
    }

    /**
     * Get printTag
     *
     * @return integer
     */
    public function getPrintTag()
    {
        return $this->printTag;
    }

    /**
     * Set printSide
     *
     * @param integer $printSide
     *
     * @return User
     */
    public function setPrintSide($printSide)
    {
        $this->printSide = $printSide;

        return $this;
    }

    /**
     * Get printSide
     *
     * @return integer
     */
    public function getPrintSide()
    {
        return $this->printSide;
    }

    /**
     * Set showCurrentOnlyDefault
     *
     * @param integer $showCurrentOnlyDefault
     *
     * @return User
     */
    public function setShowCurrentOnlyDefault($showCurrentOnlyDefault)
    {
        $this->showCurrentOnlyDefault = $showCurrentOnlyDefault;

        return $this;
    }

    /**
     * Get showCurrentOnlyDefault
     *
     * @return integer
     */
    public function getShowCurrentOnlyDefault()
    {
        return $this->showCurrentOnlyDefault;
    }

    /**
     * Set lastActiveAt
     *
     * @param \DateTime|null $lastActiveAt
     *
     * @return User
     */
    public function setLastActiveAt($lastActiveAt)
    {
        $this->lastActiveAt = $lastActiveAt;

        return $this;
    }

    /**
     * Get lastActiveAt
     *
     * @return \DateTime|null
     */
    public function getLastActiveAt()
    {
        return $this->lastActiveAt;
    }

    /**
     * Set visual options JSON string
     *
     * @param string $visualOptionsJson
     * @return User
     */
    public function setVisualOptions($visualOptionsJson)
    {
        $this->visual_options = $visualOptionsJson;
        return $this;
    }

    /**
     * Get visual options JSON string
     *
     * @return string|null
     */
    public function getVisualOptions()
    {
        return $this->visual_options;
    }

    /**
     * Get a specific visual option by key (returns default if not set)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getVisualOption($key, $default = null)
    {
        $json = $this->visual_options;
        if (empty($json)) return $default;
        $data = json_decode($json, true);
        if (!is_array($data)) return $default;
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    /**
     * Set a specific visual option key and persist as JSON string
     *
     * @param string $key
     * @param mixed $value
     * @return User
     */
    public function setVisualOption($key, $value)
    {
        $json = $this->visual_options;
        $data = [];
        if (!empty($json)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) $data = $decoded;
        }
        $data[$key] = $value;
        $this->visual_options = json_encode($data);
        return $this;
    }

    /**
     * Convenience accessor for enhanced_decklistview option
     * @return int
     */
    public function getEnhancedDecklistview()
    {
        $v = $this->getVisualOption('enhanced_decklistview', 0);
        return (int)$v;
    }

    /**
     * Convenience setter for enhanced_decklistview
     * @param int $val
     * @return User
     */
    public function setEnhancedDecklistview($val)
    {
        $this->setVisualOption('enhanced_decklistview', (int)$val);
        return $this;
    }

    /**
     * Set isNotifAuthor
     *
     * @param boolean $isNotifAuthor
     *
     * @return User
     */
    public function setIsNotifAuthor($isNotifAuthor)
    {
        $this->isNotifAuthor = $isNotifAuthor;

        return $this;
    }

    /**
     * Get isNotifAuthor
     *
     * @return boolean
     */
    public function getIsNotifAuthor()
    {
        return $this->isNotifAuthor;
    }

    /**
     * Set isNotifCommenter
     *
     * @param boolean $isNotifCommenter
     *
     * @return User
     */
    public function setIsNotifCommenter($isNotifCommenter)
    {
        $this->isNotifCommenter = $isNotifCommenter;

        return $this;
    }

    /**
     * Get isNotifCommenter
     *
     * @return boolean
     */
    public function getIsNotifCommenter()
    {
        return $this->isNotifCommenter;
    }

    /**
     * Set isNotifMention
     *
     * @param boolean $isNotifMention
     *
     * @return User
     */
    public function setIsNotifMention($isNotifMention)
    {
        $this->isNotifMention = $isNotifMention;

        return $this;
    }

    /**
     * Get isNotifMention
     *
     * @return boolean
     */
    public function getIsNotifMention()
    {
        return $this->isNotifMention;
    }

    /**
     * Set isNotifFollow
     *
     * @param boolean $isNotifFollow
     *
     * @return User
     */
    public function setIsNotifFollow($isNotifFollow)
    {
        $this->isNotifFollow = $isNotifFollow;

        return $this;
    }

    /**
     * Get isNotifFollow
     *
     * @return boolean
     */
    public function getIsNotifFollow()
    {
        return $this->isNotifFollow;
    }

    /**
     * Set isNotifSuccessor
     *
     * @param boolean $isNotifSuccessor
     *
     * @return User
     */
    public function setIsNotifSuccessor($isNotifSuccessor)
    {
        $this->isNotifSuccessor = $isNotifSuccessor;

        return $this;
    }

    /**
     * Get isNotifSuccessor
     *
     * @return boolean
     */
    public function getIsNotifSuccessor()
    {
        return $this->isNotifSuccessor;
    }

    /**
     * Set isShareDecks
     *
     * @param boolean $isShareDecks
     *
     * @return User
     */
    public function setIsShareDecks($isShareDecks)
    {
        $this->isShareDecks = $isShareDecks;

        return $this;
    }

    /**
     * Get isShareDecks
     *
     * @return boolean
     */
    public function getIsShareDecks()
    {
        return $this->isShareDecks;
    }

    /**
     * Set isNewUI
     *
     * @param boolean $isNewUI
     *
     * @return User
     */
    public function setisNewUI($isNewUI)
    {
        $this->isNewUI = $isNewUI;

        return $this;
    }

    /**
     * Get isNewUI
     *
     * @return boolean
     */
    public function getisNewUI()
    {
        return $this->isNewUI;
    }

    /**
     * Set isAdmin
     *
     * @param boolean $isAdmin
     *
     * @return User
     */
    public function setIsAdmin($isAdmin)
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    /**
     * Get isAdmin
     *
     * @return boolean
     */
    public function getIsAdmin()
    {
        return $this->isAdmin;
    }

    /**
     * Add deck
     *
     * @param \AppBundle\Entity\Deck $deck
     *
     * @return User
     */
    public function addDeck(\AppBundle\Entity\Deck $deck)
    {
        $this->decks[] = $deck;

        return $this;
    }

    /**
     * Remove deck
     *
     * @param \AppBundle\Entity\Deck $deck
     */
    public function removeDeck(\AppBundle\Entity\Deck $deck)
    {
        $this->decks->removeElement($deck);
    }

    /**
     * Get decks
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getDecks()
    {
        return $this->decks;
    }

    /**
     * Add decklist
     *
     * @param \AppBundle\Entity\Decklist $decklist
     *
     * @return User
     */
    public function addDecklist(\AppBundle\Entity\Decklist $decklist)
    {
        $this->decklists[] = $decklist;

        return $this;
    }

    /**
     * Remove decklist
     *
     * @param \AppBundle\Entity\Decklist $decklist
     */
    public function removeDecklist(\AppBundle\Entity\Decklist $decklist)
    {
        $this->decklists->removeElement($decklist);
    }

    /**
     * Get decklists
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getDecklists()
    {
        return $this->decklists;
    }

    /**
     * Add comment
     *
     * @param \AppBundle\Entity\Comment $comment
     *
     * @return User
     */
    public function addComment(\AppBundle\Entity\Comment $comment)
    {
        $this->comments[] = $comment;

        return $this;
    }

    /**
     * Remove comment
     *
     * @param \AppBundle\Entity\Comment $comment
     */
    public function removeComment(\AppBundle\Entity\Comment $comment)
    {
        $this->comments->removeElement($comment);
    }

    /**
     * Get comments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Add review
     *
     * @param \AppBundle\Entity\Review $review
     *
     * @return User
     */
    public function addReview(\AppBundle\Entity\Review $review)
    {
        $this->reviews[] = $review;

        return $this;
    }

    /**
     * Remove review
     *
     * @param \AppBundle\Entity\Review $review
     */
    public function removeReview(\AppBundle\Entity\Review $review)
    {
        $this->reviews->removeElement($review);
    }

    /**
     * Get reviews
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReviews()
    {
        return $this->reviews;
    }

    /**
     * Add favorite
     *
     * @param \AppBundle\Entity\Decklist $favorite
     *
     * @return User
     */
    public function addFavorite(\AppBundle\Entity\Decklist $favorite)
    {
		$favorite->addFavorite($this);
        $this->favorites[] = $favorite;

        return $this;
    }

    /**
     * Remove favorite
     *
     * @param \AppBundle\Entity\Decklist $favorite
     */
    public function removeFavorite(\AppBundle\Entity\Decklist $favorite)
    {
    	$favorite->removeFavorite($this);
        $this->favorites->removeElement($favorite);
    }

    /**
     * Get favorites
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFavorites()
    {
        return $this->favorites;
    }

    /**
     * Add vote
     *
     * @param \AppBundle\Entity\Decklist $vote
     *
     * @return User
     */
    public function addVote(\AppBundle\Entity\Decklist $vote)
    {
		$vote->addVote($this);
        $this->votes[] = $vote;

        return $this;
    }

    /**
     * Remove vote
     *
     * @param \AppBundle\Entity\Decklist $vote
     */
    public function removeVote(\AppBundle\Entity\Decklist $vote)
    {
    	$vote->removeVote($this);
        $this->votes->removeElement($vote);
    }

    /**
     * Get votes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Add reviewvote
     *
     * @param \AppBundle\Entity\Review $reviewvote
     *
     * @return User
     */
    public function addReviewvote(\AppBundle\Entity\Review $reviewvote)
    {
        $reviewvote->addVote($this);
        $this->reviewvotes[] = $reviewvote;
        return $this;
    }

    /**
     * Remove reviewvote
     *
     * @param \AppBundle\Entity\Review $reviewvote
     */
    public function removeReviewvote(\AppBundle\Entity\Review $reviewvote)
    {
        $this->reviewvotes->removeElement($reviewvote);
    }

    /**
     * Get reviewvotes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getReviewvotes()
    {
        return $this->reviewvotes;
    }
    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $following;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $followers;


    /**
     * Add following
     *
     * @param \AppBundle\Entity\User $following
     *
     * @return User
     */
    public function addFollowing(\AppBundle\Entity\User $following)
    {
        $this->following[] = $following;

        return $this;
    }

    /**
     * Remove following
     *
     * @param \AppBundle\Entity\User $following
     */
    public function removeFollowing(\AppBundle\Entity\User $following)
    {
        $this->following->removeElement($following);
    }

    /**
     * Get following
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFollowing()
    {
        return $this->following;
    }

    /**
     * Add follower
     *
     * @param \AppBundle\Entity\User $follower
     *
     * @return User
     */
    public function addFollower(\AppBundle\Entity\User $follower)
    {
        $this->followers[] = $follower;

        return $this;
    }

    /**
     * Remove follower
     *
     * @param \AppBundle\Entity\User $follower
     */
    public function removeFollower(\AppBundle\Entity\User $follower)
    {
        $this->followers->removeElement($follower);
    }

    /**
     * Get followers
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getFollowers()
    {
        return $this->followers;
    }
}
