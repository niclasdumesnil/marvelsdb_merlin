<?php

namespace AppBundle\Entity;

class CardTranslation
{
    /** @var int|null */
    private $id;
    /** @var CardTranslation properties used by import */
    private $locale;

    private $code;

    private $name;

    private $subname;

    private $text;

    private $traits;

    private $flavor;

    private $errata;

    public function getId()
    {
        return $this->id;
    }
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setSubname($subname)
    {
        $this->subname = $subname;
        return $this;
    }

    public function getSubname()
    {
        return $this->subname;
    }

    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setTraits($traits)
    {
        $this->traits = $traits;
        return $this;
    }

    public function getTraits()
    {
        return $this->traits;
    }

    public function setFlavor($flavor)
    {
        $this->flavor = $flavor;
        return $this;
    }

    public function getFlavor()
    {
        return $this->flavor;
    }
    public function setErrata($errata)
    {
        $this->errata = $errata;
        return $this;
    }

    public function getErrata()
    {
        return $this->errata;
    }
}
