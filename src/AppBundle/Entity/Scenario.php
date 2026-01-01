<?php

namespace AppBundle\Entity;

class Scenario
{
    public function serialize()
    {
        return [
            'code' => $this->code,
            'villain_set_code' => $this->villainSetCode,
            'title' => $this->title,
        ];
    }

    public function toString()
    {
        return $this->title ?: $this->code;
    }

    private $id;
    private $code;
    private $villainSetCode;
    private $title;
    private $nbmodular;
    private $modularSetCodes;
    private $difficulty;
    private $text;
    private $creator;
    private $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    public function getId() { return $this->id; }

    public function setCode($v) { $this->code = $v; return $this; }
    public function getCode() { return $this->code; }

    public function setVillainSetCode($v) { $this->villainSetCode = $v; return $this; }
    public function getVillainSetCode() { return $this->villainSetCode; }

    public function setTitle($v) { $this->title = $v; return $this; }
    public function getTitle() { return $this->title; }

    public function setNbmodular($v) { $this->nbmodular = $v; return $this; }
    public function getNbmodular() { return $this->nbmodular; }

    public function setModularSetCodes($v) { $this->modularSetCodes = $v; return $this; }
    public function getModularSetCodes() { return $this->modularSetCodes; }

    public function setDifficulty($v) { $this->difficulty = $v; return $this; }
    public function getDifficulty() { return $this->difficulty; }

    public function setText($v) { $this->text = $v; return $this; }
    public function getText() { return $this->text; }

    public function setCreator($v) { $this->creator = $v; return $this; }
    public function getCreator() { return $this->creator; }

    public function getDateCreation() { return $this->dateCreation; }
}
