<?php 

namespace AppBundle\Helper;

class DeckValidationHelper
{
	
	public function __construct(AgendaHelper $agenda_helper)
	{
		$this->agenda_helper = $agenda_helper;
	}
	
		/**
	* Parse deck requirements/restrictions and convert to array
	* @param string $text
	* @return Array
	*/
	public function parseReqString($text) {
		
		$return_requirements = [];
		// seperate based on commas
		$restrictions = explode(",", $text);
		foreach($restrictions as $restriction) {
			// if we have a value then next split on :
			if (trim($restriction)){
				$matches = [];
				$params = explode(":", $restriction);
				//$text .= print_r($matches,1);	
				if (isset($params[0])){
					$type = trim($params[0]);
					$param1 = false;
					$param2 = false;
					$param3 = false;
					$param4 = false;
					
					if (isset($params[1])){
						$param1 = trim($params[1]);
					}
					if (isset($params[2])){
						$param2 = trim($params[2]);
					}
					if (isset($params[3])){
						$param3 = trim($params[3]);
					}
					if (isset($params[4])){
						$param4 = trim($params[4]);
					}
					
					if (!isset($return_requirements[$type])){
						$return_requirements[$type] = [];
					}
					$parsed = false;
					switch($type){
						case "faction":{
							$return_requirements[$type][$param1] = [
								"min" => $param2,
								"max" => $param3
							];
							break;
						}
						case "cards":{
							if ($param1 == "any"){
								$return_requirements[$type][$param1] = [
									"min" => 0,
									"max" => $param3,
									"limit" => $param2
								];
							}
							break;
						}
						case "random":{
							if ($param1 && $param2){
								$return_requirements[$type][] = [
									"target" => $param1,
									"value" => $param2
								];
							}
							break;
						}
						case "hero":{
							if ($param2){
								$return_requirements[$type] = [$param1 => $param1, $param2 => $param2];
							}else if ($param1){
								$return_requirements[$type] = [$param1 => $param1];
							}
							break;
						}
						case "card":{
							if ($param2){
								$return_requirements[$type][$param1] = [$param1 => $param1, $param2 => $param2];
							}else if ($param1){
								$return_requirements[$type][$param1] = [$param1 => $param1];
							}
							break;
						}
						case "size":{
							if ($param1){
								$return_requirements[$type] = intval($param1);
							}
							break;
						}
						default:{
							$return_requirements[$type][] = $param1;
							break;
						}
					}
				}
			}
		}
		return $return_requirements;
	}
	
	public function getInvalidCards($deck)
	{
		$invalidCards = [];
		$deck_options = json_decode($deck->getCharacter()->getdeckOptions());
		foreach ( $deck->getSlots() as $slot ) {
			if(! $this->canIncludeCard($deck, $slot, $deck_options)) {
				$invalidCards[] = $slot->getCard();
			}
		}
		return $invalidCards;
	}
	
	public function canIncludeCard($deck, $slot, $deck_options = []) {
		$card = $slot->getCard();
		$indeck = $slot->getQuantity();

		// hide heroes
		if ($card->getType()->getCode() === "hero") {
			return false;
		}

		$hero = $deck->getCharacter();
		$restrictions = $card->getRestrictions();

		// Vérification des restrictions spécifiques à la carte
		if ($restrictions){
			$parsed = $this->parseReqString($restrictions);
			if ($parsed && isset($parsed['hero']) && !isset($parsed['hero'][$hero->getCode()]) ){
				return false;
			}
		}

		// vérifier la/les faction(s) du héros vis à vis des cartes à faction multiples
		$heroFaction = $hero->getFaction() ? $hero->getFaction()->getCode() : null;

		// Autoriser si la carte a au moins une faction du héros (hors basic/hero)
		if (
			($card->getFaction()->getCode() === $heroFaction) ||
			($card->getFaction2() && $card->getFaction2()->getCode() === $heroFaction) ||
			($card->getFaction()->getCode() === 'basic')
		) {
			return true;
		}
		
		// validate deck. duplicating code from app.deck.js but in php
		if ($deck_options){
			foreach($deck_options as $option) {
				$valid = false;
				
				if (isset($option->faction) && $option->faction) {
					$faction_valid = false;
					foreach($option->faction as $faction) {
						if ($card->getFaction()->getCode() == $faction || ($card->getFaction2() && $card->getFaction2()->getCode() == $faction) ) {
							$faction_valid = true;
						}
					}
					if (!$faction_valid) {
						continue;
					}
				}
				
				if (isset($option->type) && $option->type) {
					// needs to match at least one type
					$type_valid = false;
					foreach($option->type as $type) {
						if ($card->getType()->getCode() == $type){
							$type_valid = true;
						}
					}
					if (!$type_valid){
						continue;
					}
				}
				
				if (isset($option->trait) && $option->trait) {
					// needs to match at least one type
					$trait_valid = false;
					foreach($option->trait as $trait) {
						if (strpos(strtoupper($card->getRealTraits()), strtoupper($trait)."." ) !== false){
							$trait_valid = true;
						}
					}
					if (!$trait_valid){
						continue;
					}
				}
				
				
				if (isset($option->uses) && $option->uses) {
					// needs to match at least one type
					$uses_valid = false;
					foreach($option->uses as $uses) {
						if (strpos(strtoupper($card->getRealText()), strtoupper($uses).")." ) !== false){
							$uses_valid = true;
						}
					}
					if (!$uses_valid){
						continue;
					}
				}
				
				if (isset($option->text) && $option->text) {
					// needs to match at least one type
					$text_valid = false;
					foreach($option->text as $text) {
						if (preg_match( "/".$text."/", strtolower($card->getRealText()) ) === 1){
							$text_valid = true;
						}
					}
					if (!$text_valid){
						continue;
					}
				}
				
				
				if (isset($option->level) && $option->level) {
					// needs to match at least one type
					$level_valid = false;

					if (!is_null($card->getXp()) && $option->level){
						if ($card->getXp() >= $option->level->min && $card->getXp() <= $option->level->max) {
							$level_valid = true;
						} else {
							continue;
						}
					}
				}

				if (isset($option->cost) && $option->cost !== null) {
					// Si cost est un entier, la carte doit avoir un coût inférieur ou égal à cette valeur
					if (is_int($option->cost)) {
						if ($card->getCost() > $option->cost) {
							continue;
						}
					}
					// Si cost est un tableau avec min et max, la carte doit avoir un coût dans cette plage
					if (is_array($option->cost) && isset($option->cost['min']) && isset($option->cost['max'])) {
						if ($card->getCost() < $option->cost['min'] || $card->getCost() > $option->cost['max']) {
							continue;
						}
					}
				}

				if (isset($option->aspect_limit) && $option->aspect_limit !== null) {
					// Compte le nombre d'aspects différents dans le deck (hors 'basic' et 'hero')
					$aspects = [];
					foreach ($deck->getSlots() as $slotCheck) {
						$cardCheck = $slotCheck->getCard();
						$factionCode = $cardCheck->getFaction() ? $cardCheck->getFaction()->getCode() : null;
						if ($factionCode && $factionCode !== 'basic' && $factionCode !== 'hero') {
							$aspects[$factionCode] = true;
						}
					}
					// Ajoute l'aspect de la carte candidate si ce n'est pas basic/hero
					$candidateFaction = $card->getFaction() ? $card->getFaction()->getCode() : null;
					if ($candidateFaction && $candidateFaction !== 'basic' && $candidateFaction !== 'hero') {
						$aspects[$candidateFaction] = true;
					}
					$aspectCount = count($aspects);
					if ($aspectCount > $option->aspect_limit) {
						continue;
					}
				}
				
				if (isset($option->not) && $option->not){
					return false;
				}else {
					if (isset($option->limit) && $option->limit){
						if (!isset($option->limit_count)){
							$option->limit_count = 0;
						}
						$option->limit_count += $indeck;
					}
					if (isset($option->atleast) && $option->atleast){
						if (!isset($option->atleast_count[$card->getFaction()->getCode()])){
							$option->atleast_count[$card->getFaction()->getCode()] = 0;
						}
						$option->atleast_count[$card->getFaction()->getCode()] += $indeck;
					}
					// if we exceed the limit, the deck is invalid 
					if (isset($option->limit_count) && $option->limit_count && $option->limit) {
						if ($option->limit_count > $option->limit) {
							return false;
						}
					}
					return true;
				}
				
			}
		}
		return false;
	}
	
	public function findProblem($deck)
	{
		if(!empty($this->getInvalidCards($deck))) {
			return 'invalid_cards';
		}
		
		return null;
	}
	
	public function getProblemLabel($problem) {
		if(! $problem) {
			return '';
		}
		$labels = [
				'too_few_cards' => "Contains too few cards",
				'too_many_cards' => "Contains too many cards",
				'invalid_cards' => "Contains invalid cards",
				'hero' => "Doesn't comply with the hero requirements"
		];
		if(isset($labels[$problem])) {
			return $labels[$problem];
		}
		return '';
	}
	
	
}
