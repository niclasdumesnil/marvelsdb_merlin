<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="campaign")
 */
class Campaign
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $code;

    /** @ORM\Column(type="string", length=255) */
    private $name;

    /** @ORM\Column(type="string", length=32) */
    private $type;

    /** @ORM\Column(type="text", nullable=true) */
    private $scenarios;

    /** @ORM\Column(type="text", nullable=true) */
    private $modulars;

    /** @ORM\Column(type="text", nullable=true) */
    private $scenarioNotes;

    /** @ORM\Column(type="text", nullable=true) */
    private $scenarioCounters;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignNotes;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignCounters;

    /** @ORM\Column(type="text", nullable=true) */
    private $campaignCheckbox;

    /** @ORM\Column(type="text", nullable=true) */
    private $description;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $creator;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private $image;

    /** @ORM\Column(type="datetime") */
    private $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId() { return $this->id; }

    public function setCode($v) { $this->code = $v; return $this; }
    public function getCode() { return $this->code; }

    public function setName($v) { $this->name = $v; return $this; }
    public function getName() { return $this->name; }

    public function setType($v) { $this->type = $v; return $this; }
    public function getType() { return $this->type; }

    public function setScenarios($v) { $this->scenarios = $v; return $this; }
    public function getScenarios() { return $this->scenarios; }

    public function setModulars($v) { $this->modulars = $v; return $this; }
    public function getModulars() { return $this->modulars; }

    public function setScenarioNotes($v) { $this->scenarioNotes = $v; return $this; }
    public function getScenarioNotes() { return $this->scenarioNotes; }

    public function setScenarioCounters($v) { $this->scenarioCounters = $v; return $this; }
    public function getScenarioCounters() { return $this->scenarioCounters; }

    public function setCampaignNotes($v) { $this->campaignNotes = $v; return $this; }
    public function getCampaignNotes() { return $this->campaignNotes; }

    public function setCampaignCounters($v) { $this->campaignCounters = $v; return $this; }
    public function getCampaignCounters() { return $this->campaignCounters; }

    public function setCampaignCheckbox($v) { $this->campaignCheckbox = $v; return $this; }
    public function getCampaignCheckbox() { return $this->campaignCheckbox; }

    public function setDescription($v) { $this->description = $v; return $this; }
    public function getDescription() { return $this->description; }

    public function setCreator($v) { $this->creator = $v; return $this; }
    public function getCreator() { return $this->creator; }

    public function setImage($v) { $this->image = $v; return $this; }
    public function getImage() { return $this->image; }

    public function getDateCreation() { return $this->dateCreation; }
}
