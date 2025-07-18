<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Decklist;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use \DateTime;

class ApiController extends Controller
{

	/**
	 * Get the description of all the packs as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Pack",
	 *  resource=true,
	 *  description="All the Packs",
	 *  parameters={
	 *    {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 * )
	 * @param Request $request
	 */
	public function listPacksAction(Request $request)
	{
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
            'Access-Control-Allow-Origin' => '*',
            'Content-Language' => $locale
        ));

		$jsonp = $request->query->get('jsonp');

		$list_packs = $this->getDoctrine()->getRepository('AppBundle:Pack')->findAll();

		// check the last-modified-since header

		$lastModified = NULL;
		/* @var $pack \AppBundle\Entity\Pack */
		foreach($list_packs as $pack) {
			if(!$lastModified || $lastModified < $pack->getDateUpdate()) {
				$lastModified = $pack->getDateUpdate();
			}
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		// build the response

		$packs = array();
		/* @var $pack \AppBundle\Entity\Pack */
		foreach($list_packs as $pack) {
			$real = count($pack->getCards());
			$max = $pack->getSize();
			$packs[] = array(
					"name" => $pack->getName(),
					"code" => $pack->getCode(),
					"position" => $pack->getPosition(),
					"available" => $pack->getDateRelease() ? $pack->getDateRelease()->format('Y-m-d') : '',
					"known" => intval($real),
					"total" => $max,
					"url" => $this->get('router')->generate('cards_list', array('pack_code' => $pack->getCode()), UrlGeneratorInterface::ABSOLUTE_URL),
					"id" => $pack->getId(),
					"pack_type"=> $pack-> getPackType(), 
					"status" => $pack->getStatus() ?? "Official", // valeur par défaut
					"creator" => $pack->getCreator() ?? "FFG", // valeur par défaut
					"theme" => $pack->getTheme() ?? "Marvel", // valeur par défaut
       				"visibility" => $pack->getVisibility() ?? "true" // valeur par défaut
			);
		}

		$content = json_encode($packs);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;
	}

	/**
	 * Get the description of a card as a JSON object.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="One Card",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
     *      {
     *          "name"="card_code",
     *          "dataType"="string",
     *          "description"="The code of the card to get, e.g. '01001'"
     *      },
     *      {
     *          "name"="_format",
     *          "dataType"="string",
     *          "requirement"="json",
     *          "description"="The format of the returned data. Only 'json' is supported at the moment."
     *      }
     *  },
	 * )
	 * @param Request $request
	 */
	public function getCardAction($card_code, Request $request)
	{
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
			'Access-Control-Allow-Origin' => '*',
			'Content-Language' => $locale
		));
		$jsonp = $request->query->get('jsonp');

		//$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findByCode($card_code);

		// check the last-modified-since header

		$lastModified = NULL;
		/* @var $card \AppBundle\Entity\Card */
		if(!$lastModified || $lastModified < $card->getDateUpdate()) {
			$lastModified = $card->getDateUpdate();
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		/* @var $card \AppBundle\Entity\Card */
		$card = $this->get('cards_data')->getCardInfo($card, true, "en");

		$content = json_encode($card);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}



	/**
	 * Get the faq entry for a given card
	 *
	 * @ApiDoc(
	 *  section="Faq",
	 *  resource=true,
	 *  description="One Faq Entry",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
     *      {
     *          "name"="card_code",
     *          "dataType"="string",
     *          "description"="The code of the card to get the faq for, e.g. '01001'"
     *      },
     *      {
     *          "name"="_format",
     *          "dataType"="string",
     *          "requirement"="json",
     *          "description"="The format of the returned data. Only 'json' is supported at the moment."
     *      }
     *  },
	 * )
	 * @param Request $request
	 */
	public function getFaqAction($card_code, Request $request)
	{

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(array("code" => $card_code));
		$faqs = $this->get('cards_data')->get_faqs($card);
		// check the last-modified-since header

		$lastModified = NULL;
		/* @var $card \AppBundle\Entity\Card */
		if(!$lastModified || $lastModified < $card->getDateUpdate()) {
			$lastModified = $card->getDateUpdate();
		}
		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		// build the response

		/* @var $card \AppBundle\Entity\Card */
		$card = $this->get('cards_data')->getCardInfo($card, true, "en");

		$content = json_encode($faqs);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}


	/**
	 * Get the description of all the factions as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Faction",
	 *  resource=true,
	 *  description="All the Factions",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 * )
	 * @param Request $request
	 */
	public function listFactionsAction(Request $request)
	{
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
            'Access-Control-Allow-Origin' => '*',
            'Content-Language' => $locale
        ));

		$jsonp = $request->query->get('jsonp');

		$list_factions = $this->getDoctrine()->getRepository('AppBundle:Faction')->findAll();

		// check the last-modified-since header

		$lastModified = NULL;

		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		// build the response

		$factions = array();
		/* @var $card \AppBundle\Entity\Card */
		foreach($list_factions as $faction) {
			$factions[] = $faction->serialize();
		}

		$content = json_encode($factions);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}

	/**
	 * Get the description of all the cards as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="All the Cards",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 * )
	 * @param Request $request
	 */
	public function listCardsAction(Request $request)
	{
		ini_set('zlib.output_compression', 1);
		$locale = $request->getLocale();

		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array(
            'Access-Control-Allow-Origin' => '*',
            'Content-Language' => $locale
        ));

		$jsonp = $request->query->get('jsonp');
		$include_encounter = $request->query->get('encounter');


		if ($include_encounter){
			$file = "cards-all-";
		}else {
			$file = "cards-player-";
		}
		$file .= $locale.".json";

		$webdir = $this->container->get('kernel')->getRootDir() . "/../web";
		$file = $webdir."/".$file;

		if (file_exists($file)) {
			$date = new \DateTime();
			$lastModified = $date->setTimestamp(filemtime($file));
			$response->setLastModified($lastModified);
			if ($response->isNotModified($request)) {
				return $response;
			}
			$content = file_get_contents($file);
		} else {

			if ($include_encounter){
				$list_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAll();
			}else {
				$list_cards = $this->getDoctrine()->getRepository('AppBundle:Card')->findAllWithoutEncounter();
			}

			// check the last-modified-since header

			$lastModified = NULL;
			/* @var $card \AppBundle\Entity\Card */
			foreach($list_cards as $card) {
				if(!$lastModified || $lastModified < $card->getDateUpdate()) {
					$lastModified = $card->getDateUpdate();
				}
			}
			$response->setLastModified($lastModified);
			if ($response->isNotModified($request)) {
				return $response;
			}

			// build the response

			$cards = array();
			/* @var $card \AppBundle\Entity\Card */
			foreach($list_cards as $card) {
				$cards[] = $this->get('cards_data')->getCardInfo($card, true, "en");
			}

			$content = json_encode($cards);
		}
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);
		return $response;

	}


	/**
	 * Get the description of all the card from a pack, as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="All the Cards from One Pack",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
     *      {
     *          "name"="pack_code",
     *          "dataType"="string",
     *          "description"="The code of the pack to get the cards from, e.g. 'Core'"
     *      },
     *      {
     *          "name"="_format",
     *          "dataType"="string",
     *          "requirement"="json|xml|xlsx|xls",
     *          "description"="The format of the returned data. Only 'json' is supported at the moment."
     *      }
     *  },
	 * )
	 * @param Request $request
	 */
	public function listCardsByPackAction($pack_code, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}

		$pack = $this->getDoctrine()->getRepository('AppBundle:Pack')->findOneBy(array('code' => $pack_code));
		if(!$pack) {
			$response->setStatusCode(404);
			$response->setContent(json_encode(['error' => 'Pack not found']));
			return $response;
		}

		// Nouvelle logique : récupération directe des cartes via la relation pack
		$cards = [];
		$last_modified = null;
		$cardEntities = $this->getDoctrine()->getRepository('AppBundle:Card')->findBy(['pack' => $pack]);

		foreach ($cardEntities as $cardEntity) {
			if (empty($last_modified) || $last_modified < $cardEntity->getDateUpdate()) {
				$last_modified = $cardEntity->getDateUpdate();
			}
			$cards[] = $this->get('cards_data')->getCardInfo($cardEntity, true, "en");
		}

		if ($last_modified) {
			$response->setLastModified($last_modified);
			if ($response->isNotModified($request)) {
				return $response;
			}
		}

		$content = json_encode($cards);
		if(isset($jsonp))
		{
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else
		{
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}


	/**
	 * Get the description of a decklist as a JSON object.
	 *
	 * @ApiDoc(
	 *  section="Decklist",
	 *  resource=true,
	 *  description="One Decklist",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="decklist_id",
	 *          "dataType"="integer",
	 *          "requirement"="\d+",
	 *          "description"="The numeric identifier of the decklist"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
	 */
	public function getDecklistAction($decklist_id, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}

		/* @var $decklist \AppBundle\Entity\Decklist */
		$decklist = $this->getDoctrine()->getRepository('AppBundle:Decklist')->find($decklist_id);
		if(!$decklist) die();

		$response->setLastModified($decklist->getDateUpdate());
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($decklist);

		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}

		$response->setContent($content);
		return $response;

	}

	/**
	 * Get the description of all the decklists published at a given date, as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Decklist",
	 *  resource=true,
	 *  description="All the Decklists from One Day",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
     *      {
     *          "name"="date",
     *          "dataType"="string",
     *          "requirement"="\d\d\d\d-\d\d-\d\d",
     *          "description"="The date, format 'Y-m-d'"
     *      },
     *      {
     *          "name"="_format",
     *          "dataType"="string",
     *          "requirement"="json",
     *          "description"="The format of the returned data. Only 'json' is supported at the moment."
     *      }
     *  },
	 * )
	 * @param Request $request
	 */
	public function listDecklistsByDateAction($date, Request $request)
	{
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');
			return $response;
		}

		$start = \DateTime::createFromFormat('Y-m-d', $date);
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->add(new \DateInterval("P1D"));

		$expr = Criteria::expr();
		$criteria = Criteria::create();
		$criteria->where($expr->gte('dateCreation', $start));
		$criteria->andWhere($expr->lt('dateCreation', $end));

		/* @var $decklists \Doctrine\Common\Collections\ArrayCollection */
		$decklists = $this->getDoctrine()->getRepository('AppBundle:Decklist')->matching($criteria);
		if(!$decklists) die();

		$dateUpdates = $decklists->map(function ($decklist) {
			return $decklist->getDateUpdate();
		})->toArray();

		$response->setLastModified(max($dateUpdates));
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($decklists->toArray());

		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}

		$response->setContent($content);
		return $response;

	}


	/**
	 * Get the description of one public Deck
	 *
	 * @ApiDoc(
	 *  section="Deck",
	 *  resource=true,
	 *  description="Load One Deck",
	 	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="deck_id",
	 *          "dataType"="integer",
	 *          "requirement"="\d+",
	 *          "description"="The numeric identifier of the deck"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
	 */
	public function getPublicDeckAction($deck_id, Request $request)
	{
		$response = new Response();
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		/* @var $deck \AppBundle\Entity\Deck */
		$deck = $this->getDoctrine()->getRepository('AppBundle:Deck')->find($deck_id);

		if(!$deck || !$deck->getUser() || !$deck->getUser()->getIsShareDecks()) {
			throw $this->createAccessDeniedException("Access denied to this object.");
		}

		$response->setLastModified($deck->getDateUpdate());
		if ($response->isNotModified($request)) {
			return $response;
		}

		$deck->setUser(null);

		$content = json_encode($deck);

		$response->headers->set('Content-Type', 'application/json');
		$response->setContent($content);
		return $response;
	}
}
