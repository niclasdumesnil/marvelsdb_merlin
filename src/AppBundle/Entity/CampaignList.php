<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="campaignlist")
 */
class CampaignList
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Many CampaignLists belong to one Campaign (static definition)
     * @ORM\ManyToOne(targetEntity="AppBundle\\Entity\\Campaign")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    private $campaign;

    /**
     * Many CampaignLists can reference a Team entity (optional)
     * @ORM\ManyToOne(targetEntity="AppBundle\\Entity\\Team")
     * @ORM\JoinColumn(name="team_id", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     */
    private $team;

    /** @ORM\Column(type="text", nullable=true) */
    private $teamData;

    /** @ORM\Column(type="text", nullable=true) */
    private $teamHps;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignCards;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignCheckboxes;

    

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignNotes;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignCounters;

    /** @ORM\Column(type="text", nullable=true) */
    private $playerNames;

    /** @ORM\Column(type="integer", nullable=true) */
    private $selectedScenario;

    /** @ORM\Column(type="datetime") */
    private $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId() { return $this->id; }

    public function setCampaign($v) { $this->campaign = $v; return $this; }
    public function getCampaign() { return $this->campaign; }

    public function setTeam($teamEntity) { $this->team = $teamEntity; return $this; }
    public function getTeam() { return $this->team; }

    public function setTeamData($v) { $this->teamData = $v; return $this; }
    public function getTeamData() { return $this->teamData; }

    public function setTeamHps($v) { $this->teamHps = $v; return $this; }
    public function getTeamHps() { return $this->teamHps; }

    public function setCampaignCards($v) { $this->campaignCards = $v; return $this; }
    public function getCampaignCards() { return $this->campaignCards; }

    public function setCampaignCheckboxes($v) { $this->campaignCheckboxes = $v; return $this; }
    public function getCampaignCheckboxes() { return $this->campaignCheckboxes; }

    

    public function setCampaignNotes($v) { $this->campaignNotes = $v; return $this; }
    public function getCampaignNotes() { return $this->campaignNotes; }

    public function setCampaignCounters($v) { $this->campaignCounters = $v; return $this; }
    public function getCampaignCounters() { return $this->campaignCounters; }

    public function setPlayerNames($v) { $this->playerNames = $v; return $this; }
    public function getPlayerNames() { return $this->playerNames; }

    public function setSelectedScenario($v) { $this->selectedScenario = $v; return $this; }
    public function getSelectedScenario() { return $this->selectedScenario; }

    public function getDateCreation() { return $this->dateCreation; }
}
