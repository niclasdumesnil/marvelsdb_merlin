<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Model\DecklistManager;
use AppBundle\Entity\Decklist;

class DefaultController extends Controller
{

	public function indexAction(\Symfony\Component\HttpFoundation\Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		/**
		* @var $decklist_manager DecklistManager
		*/
		$decklist_manager = $this->get('decklist_manager');
		$decklist_manager->setLimit(50);

		$typeNames = [];
		foreach($this->getDoctrine()->getRepository('AppBundle:Type')->findAll() as $type) {
			$typeNames[$type->getCode()] = $type->getName();
		}

		$decklists_by_popular = [];
		$decklists_by_recent = [];
		$decklists_by_hero = [];
		$dupe_deck_list = [];

		$factions = $this->getDoctrine()->getRepository('AppBundle:Faction')->findBy(['isPrimary' => true], ['code' => 'ASC']);

		$type = $this->getDoctrine()->getRepository('AppBundle:Type')->findOneBy(['code' => 'hero'], ['id' => 'DESC']);
		$cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findBy(['type' => $type], ['id' => 'ASC']);

		$date1 = strtotime('2024-03-30');
		$date2 = time();

		$year1 = date('Y', $date1);
		$year2 = date('Y', $date2);

		$month1 = date('m', $date1);
		$month2 = date('m', $date2);

		// $diff = (($year2 - $year1) * 12) + ($month2 - $month1);
		$diff = $date2 - $date1;
		$weeks_since = ($diff / (60 * 60 * 24 * 7));
		if ($weeks_since >= 0 && $weeks_since < count($cards)) {
			$card = $cards[$weeks_since];
			if (!$card->getMeta() || $card->getPack()->GetVisibility()) {
				$card = $cards[$weeks_since - 1];
			}
			if (!$card->getMeta() || $card->getPack()->GetVisibility()) {
				$card = $cards[$weeks_since - 2];
			}
		} else {
			throw new \Exception("Ran out of heroes for spotlight.");
		}

		// HERO : max 3 decks, un seul deck par utilisateur
		$paginator = $decklist_manager->findDecklistsByHero($card, true);
		$iterator = $paginator->getIterator();
		$userCheckHero = [];
		$decklists_by_hero = [];
		$hero_deck_ids = [];
		while($iterator->valid() && count($decklists_by_hero) < 3)
		{
			$decklist = $iterator->current();
			$hero = $decklist->getCharacter();
			if (!isset($userCheckHero[$decklist->getUser()->getId()])){
				$decklists_by_hero[] = [
					'hero_meta' => json_decode($hero->getMeta()),
					'faction' => $hero->getFaction(),
					'decklist' => $decklist,
					'meta' => json_decode($decklist->getMeta())
				];
				$userCheckHero[$decklist->getUser()->getId()] = true;
				$hero_deck_ids[] = $decklist->getId();
			}
			$iterator->next();
		}

		// POPULAR : max 3 decks, un seul deck par utilisateur, exclure ceux de la hero list
		$paginator = $decklist_manager->findDecklistsByTrending();
		$iterator = $paginator->getIterator();
		$userCheckPopular = [];
		$decklists_by_popular = [];
		$popular_deck_ids = [];
		while($iterator->valid() && count($decklists_by_popular) < 3)
		{
			$decklist = $iterator->current();
			if (
				$decklist->getCharacter()->getCode() != $card->getCode()
				&& $decklist->getCharacter()->getPack()->GetVisibility() != "false"
				&& !isset($userCheckPopular[$decklist->getUser()->getId()])
				&& !in_array($decklist->getId(), $hero_deck_ids) // Exclure ceux de la hero list
			)
			{
				$decklists_by_popular[] = [
					'hero_meta' => json_decode($decklist->getCharacter()->getMeta()),
					'faction' => $decklist->getCharacter()->getFaction(),
					'decklist' => $decklist,
					'meta' => json_decode($decklist->getMeta())
				];
				$userCheckPopular[$decklist->getUser()->getId()] = true;
				$popular_deck_ids[] = $decklist->getId();
			}
			$iterator->next();
		}

		// RECENT : max 3 decks, exclure ceux de la hero et popular list (plus de restriction par utilisateur)
		$paginator = $decklist_manager->findDecklistsByAge(true);
		$iterator = $paginator->getIterator();
		$decklists_by_recent = [];
		while($iterator->valid() && count($decklists_by_recent) < 3)
		{
			$decklist = $iterator->current();
			if (
				$decklist->getCharacter()->getCode() != $card->getCode()
				&& $decklist->getCharacter()->getPack()->GetVisibility() != "false"
				&& !in_array($decklist->getId(), $hero_deck_ids)
				&& !in_array($decklist->getId(), $popular_deck_ids)
			) {
				$decklists_by_recent[] = [
					'hero_meta' => json_decode($decklist->getCharacter()->getMeta()),
					'faction' => $decklist->getCharacter()->getFaction(),
					'decklist' => $decklist,
					'meta' => json_decode($decklist->getMeta())
				];
			}
			$iterator->next();
		}

		$date1 = strtotime('2022-06-19');
		$date2 = time();

		$year1 = date('Y', $date1);
		$year2 = date('Y', $date2);

		$month1 = date('m', $date1);
		$month2 = date('m', $date2);

		// $diff = (($year2 - $year1) * 12) + ($month2 - $month1);
		$diff = $date2 - $date1;
		$days_since = ($diff / (60 * 60 * 24));
		$cards_offset = [0,10,20];
		if ($days_since >= 0) {
			$card_offset = $cards_offset[$days_since % 3] + (floor($days_since / 3));
			$cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findBy(
				['card_set' => null, 'duplicate_of' => null], ['id' => 'ASC'], 1, $card_offset
			);
			if (count($cards) > 0) {
				$card_of_the_day = $cards[0];
				// Vérifie que la carte n'est pas privée et que son pack est visible
				if (
					(method_exists($card_of_the_day, 'GetVisibility') && $card_of_the_day->GetVisibility()) ||
					(method_exists($card_of_the_day->getPack(), 'GetVisibility') && $card_of_the_day->getPack()->GetVisibility())
				) {
					// Cherche la carte suivante non privée et visible
					$found = false;
					for ($i = $card_offset + 1; $i < $card_offset + 10 && !$found; $i++) {
						$next_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findBy(
							['card_set' => null, 'duplicate_of' => null], ['id' => 'ASC'], 1, $i
						);
						if (count($next_cards) > 0) {
							$next_card = $next_cards[0];
							if (
								(!method_exists($next_card, 'GetVisibility') || !$next_card->GetVisibility()) &&
								(!method_exists($next_card->getPack(), 'GetVisibility') || !$next_card->getPack()->GetVisibility())
							) {
								$card_of_the_day = $next_card;
								$found = true;
							}
						}
					}
					if (!$found) {
						throw new \Exception("Ran out of public cards for card of the day.");
					}
				}
			} else {
				throw new \Exception("Ran out of heroes for spotlight.");
			}
		} else {
			throw new \Exception("Ran out of heroes for spotlight.");
		}

		$card_of_the_day_info = $this->get('cards_data')->getCardInfo($card_of_the_day, false, false);
		$paginator = $decklist_manager->findDecklistsByCard($card_of_the_day, true);
		$iterator = $paginator->getIterator();
		$card_of_the_day_decklists = [];
		$no_dupe_heroes = [];
		while($iterator->valid() && count($card_of_the_day_decklists) < 3)
		{
			$decklist = $iterator->current();
			if (!isset($no_dupe_heroes[$decklist->getCharacter()->getId()])) {
				$card_of_the_day_decklists[] = [
					'hero_meta' => json_decode($decklist->getCharacter()->getMeta()),
					'faction' => $decklist->getCharacter()->getFaction(),
					'decklist' => $decklist,
					'meta' => json_decode($decklist->getMeta())
				];
				$no_dupe_heroes[$decklist->getCharacter()->getId()] = true;
			}
			$iterator->next();
		}

		$game_name = $this->container->getParameter('game_name');
		$publisher_name = $this->container->getParameter('publisher_name');

		$sort = $request->query->get('fanpacks_sort', 'date');
		$packs = $this->getDoctrine()->getRepository('AppBundle:Pack')->findBy([], ['dateRelease' => 'DESC']);

		// Filtrer les packs privés si l'utilisateur n'est pas connecté ou pas donateur
		$user = $this->getUser();
		$isDonator = $user && method_exists($user, 'isDonator') && $user->isDonator();

		if (!$user || !$isDonator) {
			$packs = array_filter($packs, function($pack) {
				// On considère que getVisibility() retourne "false" pour les packs privés
				return $pack->getVisibility() !== "false";
			});
		}

		// Filtrer les packs dont le creator est vide ou "FFG"
		$packs = array_filter($packs, function($pack) {
			$creator = $pack->getCreator();
			return !empty($creator) && strtoupper(trim($creator)) !== 'FFG';
		});

		if ($sort === 'creator') {
			usort($packs, function($a, $b) {
				return strcasecmp($a->getCreator(), $b->getCreator());
			});
		} elseif ($sort === 'alpha') {
			usort($packs, function($a, $b) {
				return strcasecmp($a->getName(), $b->getName());
			});
		}

		// Ajout du nombre d'utilisateurs
		$user_count = $this->getDoctrine()->getRepository('AppBundle:User')->count([]);

		// Comptage des decks publics (publiés)
		$public_deck_count = $this->getDoctrine()->getRepository('AppBundle:Decklist')->count([]);

		return $this->render('AppBundle:Default:index.html.twig', [
			'pagetitle' =>  "$game_name Deckbuilder",
			'pagedescription' => "Build your deck for $game_name by $publisher_name. Browse the cards and the thousand of decklists submitted by the community. Publish your own decks and get feedback.",
			'decklists_by_popular' => $decklists_by_popular,
			'decklists_by_recent' => $decklists_by_recent,
			'hero_highlight' => $card,
			'hero_highlight_meta' => json_decode($card->getMeta()),
			'card_of_the_day' => $card_of_the_day_info,
			'card_of_the_day_decklists' => $card_of_the_day_decklists,
			'decklists_by_hero' => $decklists_by_hero,
			'packs' => array_slice($packs, 0, 3), // Limit 
			'user_count' => $user_count,
			'public_deck_count' => $public_deck_count,
		
		], $response);
	}

	function rulesAction()
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		$page = $this->renderView('AppBundle:Default:rulesreference.html.twig',
		array("pagetitle" => "Rules", "pagedescription" => "Rules Reference"));
		$response->setContent($page);
		return $response;
	}

	function aboutAction()
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		return $this->render('AppBundle:Default:about.html.twig', array(
		"pagetitle" => "About",
		"game_name" => $this->container->getParameter('game_name'),
		), $response);
	}

	function apiIntroAction()
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));

		return $this->render('AppBundle:Default:apiIntro.html.twig', array(
		"pagetitle" => "API",
		"game_name" => $this->container->getParameter('game_name'),
		"publisher_name" => $this->container->getParameter('publisher_name'),
		), $response);
	}
}
