<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Cardset;
use AppBundle\Entity\Pack;
use AppBundle\Entity\Card;

class ImportStdCommand extends ContainerAwareCommand
{
	/* @var $em EntityManager */
	private $em;

	private $links = [];
	private $duplicates = [];

	/* @var $output OutputInterface */
	private $output;

	private $collections = [];

	protected function configure()
	{
		$this
		->setName('app:import:std')
		->setDescription('Import cards data file in json format from a copy of https://github.com/zzorba/marvels-json-data')
		->addArgument(
				'path',
				InputArgument::REQUIRED,
				'Path to the repository'
				);

		$this->addOption(
				'player',
				null,
				InputOption::VALUE_NONE,
				'Only player cards'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$path = $input->getArgument('path');
		$player_only = $input->getOption('player');
		$this->em = $this->getContainer()->get('doctrine')->getEntityManager();
		$this->output = $output;

		/* @var $helper \Symfony\Component\Console\Helper\QuestionHelper */
		$helper = $this->getHelper('question');
		//$this->loadCollection('Card');
		// factions

		$output->writeln("Importing Classes...");
		$factionsFileInfo = $this->getFileInfo($path, 'factions.json');
		$imported = $this->importFactionsJsonFile($factionsFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Faction');
		$this->collections['Faction2'] = $this->collections['Faction'];
		$output->writeln("Done.");

		// factions fan made

		$output->writeln("Importing Fan made Classes...");
		$factionsFileInfo = $this->getFileInfo($path, 'factions_fanmade.json');
		$imported = $this->importFactionsJsonFile($factionsFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		
		$this->em->flush();
		$this->loadCollection('Faction');
		$this->collections['Faction2'] = $this->collections['Faction'];
		$output->writeln("Done.");
		
		// types

		$output->writeln("Importing Types...");
		$typesFileInfo = $this->getFileInfo($path, 'types.json');
		$imported = $this->importTypesJsonFile($typesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Type');
		$output->writeln("Done.");

		// fanmade types

		$output->writeln("Importing Fan made Types...");
		$typesFileInfo = $this->getFileInfo($path, 'types_fanmade.json');
		$imported = $this->importTypesJsonFile($typesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Type');
		$output->writeln("Done.");

		// subtypes

		$output->writeln("Importing SubTypes...");
		$subtypesFileInfo = $this->getFileInfo($path, 'subtypes.json');
		$imported = $this->importSubtypesJsonFile($subtypesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Subtype');
		$output->writeln("Done.");

		// packtypes

		$output->writeln("Importing PackTypes...");
		$packtypesFileInfo = $this->getFileInfo($path, 'packtypes.json');
		$imported = $this->importPacktypesJsonFile($packtypesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Packtype');
		$output->writeln("Done.");

		// packtypes fan made
	
		$output->writeln("Importing Fan made PackTypes...");
		$packtypesFileInfo = $this->getFileInfo($path, 'packtypes_fanmade.json');
		$imported = $this->importPacktypesJsonFile($packtypesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		
		$this->em->flush();
		$this->loadCollection('Packtype');
		$output->writeln("Done.");
		
		$output->writeln("Importing CardsetTypes...");
		$cardsettypesFileInfo = $this->getFileInfo($path, 'settypes.json');
		$imported = $this->importCardsettypesJsonFile($cardsettypesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Cardsettype');
		$output->writeln("Done.");

		$output->writeln("Importing CardsetTypes fanmade...");
		$cardsettypesFileInfo = $this->getFileInfo($path, 'settypes_fanmade.json');
		$imported = $this->importCardsettypesJsonFile($cardsettypesFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Cardsettype');
		$output->writeln("Done.");

		// card sets

		$output->writeln("Importing Card Sets...");
		$setsFileInfo = $this->getFileInfo($path, 'sets.json');
		$imported = $this->importCardSetsJsonFile($setsFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Cardset');
		$output->writeln("Done.");

		// card sets Fan made

		$output->writeln("Importing FM Card Sets...");
		
		$setsFileInfo = $this->getFileInfo($path, 'sets_fanmade.json');
		$imported = $this->importCardSetsJsonFile($setsFileInfo);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Cardset');
		$output->writeln("Done.");
		
		// second, packs

		$output->writeln("Importing Packs...");
		$packsFileInfo = $this->getFileInfo($path, 'packs.json');
		$imported = $this->importPacksJsonFile($packsFileInfo);
		$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Pack');
		$output->writeln("Done.");

		// packs Fan Made

		$packsFileInfo = $this->getFileInfo($path, 'packs_fanmade.json');
		$imported = $this->importPacksJsonFile($packsFileInfo);
		$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		$this->loadCollection('Pack');
		$output->writeln("Done.");
		
		// third, cards

		$output->writeln("Importing Cards...");
		$imported = [];
		// get subdirs of files and do this for each file
		$scanned_directory = array_diff(scandir($path."/pack"), array('..', '.'));
		$fileSystemIterator = $this->getFileSystemIterator($path."/pack/");
		foreach ($fileSystemIterator as $fileinfo) {
			$imported = array_merge($imported, $this->importCardsJsonFile($fileinfo, $player_only));
		}
		if(count($imported)) {
			$question = new ConfirmationQuestion("Do you confirm? (Y/n) ", true);
			if(!$helper->ask($input, $output, $question)) {
				die();
			}
		}
		$this->em->flush();
		// reload the cards so we can link cards
		if ($this->links && count($this->links) > 0){
			$output->writeln("Resolving Links");
			$this->loadCollection('Card');
			foreach($this->links as $link){
				$card = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $link['card_id']]);
				$target = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $link['target_id']]);
				if ($card && $target){
					$card->setLinkedTo($target);
					$target->setLinkedTo();
					$output->writeln("Importing link between ".$card->getName()." and ".$target->getName().".");
				}
			}
			$this->em->flush();
		}

		// go over duplicates and create them based on the cards they are duplicating
		if ($this->duplicates && count($this->duplicates) > 0) {
			$output->writeln("Resolving Duplicates");
			$this->loadCollection('Card');
			foreach($this->duplicates as $duplicate) {
				$duplicate_of = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $duplicate['duplicate_of']]);
				$new_card = $duplicate['card'];
				// create a new "card" using the data of this card.
				$new_card_data = $duplicate_of->serialize();
				$new_card_data['code'] = $new_card['code'];
				$new_card_data['duplicate_of'] = $duplicate['duplicate_of'];
				if (isset($new_card['pack_code'])) {
					$new_card_data['pack_code'] = $new_card['pack_code'];
				}
				if (isset($new_card['position'])) {
					$new_card_data['position'] = $new_card['position'];
				}
				if (isset($new_card['quantity'])) {
					$new_card_data['quantity'] = $new_card['quantity'];
				}
				if (isset($new_card['flavor'])) {
					$new_card_data['flavor'] = $new_card['flavor'];
				}
				$new_cards = [];
				$new_cards[] = $new_card_data;
				$duplicates_added = $this->importCardsFromJsonData($new_cards);
				print_r(count($duplicates_added));
				if ($duplicates_added && isset($duplicates_added[0])) {
					$duplicates_added[0]->setDuplicateOf($duplicate_of);
					//print_r($new_card_data);
				}
			}

			$this->em->flush();
		}


		$output->writeln("");
		// Import campaign lists from campaigns.json (if provided)
		try {
			$campaignsFile = $this->getFileInfo($path, 'campaigns.json');
			$importedCampaignLists = $this->importCampaignlistsJsonFile($campaignsFile);
			if(count($importedCampaignLists)) {
				// If running non-interactive (eg. CI or -n), persist automatically.
				$shouldImport = !$input->isInteractive();
				if (!$shouldImport) {
					$question = new ConfirmationQuestion("Do you confirm importing campaign lists? (Y/n) ", true);
					$shouldImport = (bool) $helper->ask($input, $output, $question);
				}
				if ($shouldImport) {
					foreach($importedCampaignLists as $c) {
						$this->em->persist($c);
					}
					$this->em->flush();
				}
			}
			} catch (\Exception $e) {
				if ($this->output) $this->output->writeln("Skipping campaigns import: " . $e->getMessage());
			}

		$output->writeln("Generate cards json.");
		$doctrine = $this->getContainer()->get('doctrine');

		$supported_locales = $this->getContainer()->getParameter('supported_locales');
		$default_locale = $this->getContainer()->getParameter('locale');

		// Ajout d'une variable pour contrôler la génération multilingue
		$generate_all_locales = false; // Passe à false pour ne faire que l'EN

		foreach($supported_locales as $supported_locale) {
			if (!$generate_all_locales && $supported_locale !== 'en') {
				continue;
			}
			$doctrine->getRepository('AppBundle:Card')->setDefaultLocale($supported_locale);
			$list_cards = $doctrine->getRepository('AppBundle:Card')->findAll();
			// build the file
			$cards = array();
			/* @var $card \AppBundle\Entity\Card */
			foreach($list_cards as $card) {
				$cards[] = $this->getContainer()->get('cards_data')->getCardInfo($card, true, "en");
			}	
			$content = json_encode($cards);
			$webdir = $this->getContainer()->get('kernel')->getRootDir() . "/../web";
			file_put_contents($webdir."/cards-all-".$supported_locale.".json", $content);

			$list_cards = $doctrine->getRepository('AppBundle:Card')->findAllWithoutEncounter();
			// build the file
			$cards = array();
			/* @var $card \AppBundle\Entity\Card */
			foreach($list_cards as $card) {
				$cards[] = $this->getContainer()->get('cards_data')->getCardInfo($card, true, "en");
			}
			$content = json_encode($cards);
			$webdir = $this->getContainer()->get('kernel')->getRootDir() . "/../web";
			file_put_contents($webdir."/cards-player-".$supported_locale.".json", $content);
		}
		$output->writeln("Done.");

	}

	protected function importFactionsJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$faction = $this->getEntityFromData('AppBundle\\Entity\\Faction', $data, [
					'code',
					'name',
					'is_primary'
			], [], []);
			if($faction) {
				$result[] = $faction;
				$this->em->persist($faction);
			}
		}

		return $result;
	}

	protected function importTypesJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Type', $data, [
					'code',
					'name'
			], [], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

	protected function importSubtypesJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Subtype', $data, [
					'code',
					'name'
			], [], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

	protected function importPacktypesJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Packtype', $data, [
					'code',
					'name'
			], [], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

	protected function importCardsettypesJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Cardsettype', $data, [
					'code',
					'name'
			], [], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

	protected function importCardSetsJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Cardset', $data, [
					'code',
					'name'
			], [
				'card_set_type_code'
			], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}


	protected function importScenariosJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Scenario', $data, [
				'code',
				'name'
			], [
				'campaign_code'
			], []);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

	protected function importCampaignsJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$list = $this->getDataFromFile($fileinfo);
		foreach($list as $data)
		{
			$type = $this->getEntityFromData('AppBundle\\Entity\\Campaign', $data, [
						'code',
						'name',
						'size'
					], [], ['creator', 'position']);
			if($type) {
				$result[] = $type;
				$this->em->persist($type);
			}
		}

		return $result;
	}

protected function importCampaignlistsJsonFile(\SplFileInfo $fileinfo)
{
	$result = [];

	$list = $this->getDataFromFile($fileinfo);
	// Support legacy full objects or new codes-only list where each item is a campaign code string
	if (is_array($list) && count($list) > 0 && is_string(reset($list))) {
		$codes = $list;
		$list = [];
		$baseDir = dirname($fileinfo->getPathname()) . DIRECTORY_SEPARATOR . 'campaign';
		foreach ($codes as $code) {
			$campaignFile = $baseDir . DIRECTORY_SEPARATOR . $code . '.json';
			if (file_exists($campaignFile)) {
				$raw = file_get_contents($campaignFile);
				$entry = json_decode($raw, true);
				if ($entry !== null) {
					$list[] = $entry;
				} else {
					if ($this->output) $this->output->writeln("Warning: unable to decode $campaignFile");
				}
			} else {
				if ($this->output) $this->output->writeln("Warning: campaign file not found: $campaignFile");
			}
		}
	}
	if ($this->output) $this->output->writeln("Read ".(is_array($list)?count($list):0)." campaign entries from {$fileinfo->getPathname()}");
	foreach($list as $data) {
		if ($this->output) $this->output->writeln("Processing campaign: " . (isset($data['code']) ? $data['code'] : (isset($data['name']) ? $data['name'] : '(no id)')));
		// Avoid creating duplicate static Campaign rows: check by code then by name
		$repo = $this->em->getRepository('AppBundle:Campaign');
		$existing = null;
		if (isset($data['code']) && $data['code']) {
			$existing = $repo->findOneBy(['code' => $data['code']]);
		}
		if (!$existing && isset($data['name']) && $data['name']) {
			$existing = $repo->findOneBy(['name' => $data['name']]);
		}

		if ($existing) {
			// If an existing campaign is found, update its fields from the JSON
			// when those values are present so imports can enrich existing rows.
			if (isset($data['code'])) $existing->setCode($data['code']);
			if (isset($data['name'])) $existing->setName($data['name']);
			if (isset($data['size'])) $existing->setSize($data['size']);
			if (isset($data['type'])) $existing->setType($data['type']);
			if (isset($data['scenarios'])) {
				$norm = [];
				if (is_array($data['scenarios'])) {
					foreach ($data['scenarios'] as $s) {
						if (!is_array($s)) continue;
						$entry = [];
						if (isset($s['code'])) $entry['code'] = $s['code'];
						if (isset($s['name'])) $entry['name'] = $s['name'];
						if (isset($s['description'])) $entry['description'] = $s['description'];
						if (isset($s['introduction'])) $entry['introduction'] = $s['introduction'];
						if (isset($s['resolution'])) $entry['resolution'] = $s['resolution'];
						if (isset($s['image'])) $entry['image'] = $s['image'];
						if (isset($s['epilogue'])) $entry['epilogue'] = $s['epilogue'];
						$norm[] = $entry;
					}
				}
				$existing->setScenarios(json_encode($norm));
			}
			if (isset($data['modulars'])) {
				$normMod = [];
				if (is_array($data['modulars'])) {
					foreach ($data['modulars'] as $scode => $mlist) {
						$normMod[$scode] = [];
						if (!is_array($mlist)) continue;
						foreach ($mlist as $m) {
							// accept string code, object with keys, or numeric-array [name,code]
							if (is_string($m)) {
								$normMod[$scode][] = ['code' => $m, 'name' => null];
							} elseif (is_array($m)) {
								if (isset($m['code']) || isset($m['name'])) {
									$code = isset($m['code']) ? $m['code'] : (isset($m[1]) ? $m[1] : null);
									$name = isset($m['name']) ? $m['name'] : (isset($m[0]) ? $m[0] : null);
									$normMod[$scode][] = ['code' => $code, 'name' => $name];
								} else {
									// numeric indexed like [name,code]
									$name = isset($m[0]) ? $m[0] : null;
									$code = isset($m[1]) ? $m[1] : null;
									$normMod[$scode][] = ['code' => $code, 'name' => $name];
								}
							}
						}
					}
				}
				$existing->setModulars(json_encode($normMod));
			}
			// support new campaign-level keys as well as legacy scenario-level shapes
			if (isset($data['campaign_notes'])) {
				if (method_exists($existing, 'setCampaignNotes')) $existing->setCampaignNotes(json_encode($data['campaign_notes']));
				else $existing->setScenarioNotes(json_encode($data['campaign_notes']));
			} elseif (isset($data['scenario_notes'])) {
				if (method_exists($existing, 'setCampaignNotes')) $existing->setCampaignNotes(json_encode($data['scenario_notes']));
				else $existing->setScenarioNotes(json_encode($data['scenario_notes']));
			}
			if (isset($data['campaign_counters'])) {
				if (method_exists($existing, 'setCampaignCounters')) $existing->setCampaignCounters(json_encode($data['campaign_counters']));
				else $existing->setScenarioCounters(json_encode($data['campaign_counters']));
			} elseif (isset($data['scenario_counters'])) {
				if (method_exists($existing, 'setCampaignCounters')) $existing->setCampaignCounters(json_encode($data['scenario_counters']));
				else $existing->setScenarioCounters(json_encode($data['scenario_counters']));
			}
			if (isset($data['campaign_checkbox'])) {
				if (method_exists($existing, 'setCampaignCheckbox')) $existing->setCampaignCheckbox(json_encode($data['campaign_checkbox']));
			}
			if (array_key_exists('description', $data)) $existing->setDescription($data['description']);
			if (array_key_exists('epilogue', $data)) $existing->setEpilogue($data['epilogue']);
			if (isset($data['image'])) $existing->setImage($data['image']);
			if (isset($data['creator'])) $existing->setCreator($data['creator']);
			if (isset($data['position'])) $existing->setPosition($data['position']);

			$result[] = $existing;
			continue;
		}

		if ($this->output) $this->output->writeln("Imported/Updated campaigns count: " . count($result));

		// Import static campaign definition into the new Campaign entity
		// Import static campaign definition into the new Campaign entity
		$campaign = new \AppBundle\Entity\Campaign();
		if (isset($data['code'])) $campaign->setCode($data['code']);
		if (isset($data['name'])) $campaign->setName($data['name']);
		if (isset($data['type'])) $campaign->setType($data['type']);
		// store arrays as json strings for static fields
		if (isset($data['scenarios']) && is_array($data['scenarios'])) {
			$norm = [];
			foreach ($data['scenarios'] as $s) {
				if (!is_array($s)) continue;
				$entry = [];
				if (isset($s['code'])) $entry['code'] = $s['code'];
				if (isset($s['name'])) $entry['name'] = $s['name'];
				if (isset($s['description'])) $entry['description'] = $s['description'];
				if (isset($s['introduction'])) $entry['introduction'] = $s['introduction'];
				if (isset($s['resolution'])) $entry['resolution'] = $s['resolution'];
					if (isset($s['image'])) $entry['image'] = $s['image'];
					if (isset($s['epilogue'])) $entry['epilogue'] = $s['epilogue'];
				$norm[] = $entry;
			}
			$campaign->setScenarios(json_encode($norm));
		} else {
			$campaign->setScenarios(null);
		}
		if (isset($data['modulars']) && is_array($data['modulars'])) {
			$normMod = [];
			foreach ($data['modulars'] as $scode => $mlist) {
				$normMod[$scode] = [];
				if (!is_array($mlist)) continue;
				foreach ($mlist as $m) {
					if (is_string($m)) {
						$normMod[$scode][] = ['code' => $m, 'name' => null];
					} elseif (is_array($m)) {
						if (isset($m['code']) || isset($m['name'])) {
							$code = isset($m['code']) ? $m['code'] : (isset($m[1]) ? $m[1] : null);
							$name = isset($m['name']) ? $m['name'] : (isset($m[0]) ? $m[0] : null);
							$normMod[$scode][] = ['code' => $code, 'name' => $name];
						} else {
							$name = isset($m[0]) ? $m[0] : null;
							$code = isset($m[1]) ? $m[1] : null;
							$normMod[$scode][] = ['code' => $code, 'name' => $name];
						}
					}
				}
			}
			$campaign->setModulars(json_encode($normMod));
		} else {
			$campaign->setModulars(null);
		}
		// store notes/counters definitions (static) on Campaign - support campaign-level keys
		if (method_exists($campaign, 'setCampaignNotes')) {
			$campaign->setCampaignNotes(isset($data['campaign_notes']) ? json_encode($data['campaign_notes']) : (isset($data['scenario_notes']) ? json_encode($data['scenario_notes']) : null));
		} else {
			$campaign->setScenarioNotes(isset($data['campaign_notes']) ? json_encode($data['campaign_notes']) : (isset($data['scenario_notes']) ? json_encode($data['scenario_notes']) : null));
		}
		if (method_exists($campaign, 'setCampaignCounters')) {
			$campaign->setCampaignCounters(isset($data['campaign_counters']) ? json_encode($data['campaign_counters']) : (isset($data['scenario_counters']) ? json_encode($data['scenario_counters']) : null));
		} else {
			$campaign->setScenarioCounters(isset($data['campaign_counters']) ? json_encode($data['campaign_counters']) : (isset($data['scenario_counters']) ? json_encode($data['scenario_counters']) : null));
		}
		// campaign checkbox definitions (optional)
		if (isset($data['campaign_checkbox'])) {
			if (method_exists($campaign, 'setCampaignCheckbox')) $campaign->setCampaignCheckbox(json_encode($data['campaign_checkbox']));
		}

		// optional descriptive fields (accept empty string/null to overwrite existing value)
		if (array_key_exists('description', $data)) {
			$campaign->setDescription($data['description']);
		}
		if (isset($data['image'])) {
			$campaign->setImage($data['image']);
		}
		if (isset($data['creator'])) {
			$campaign->setCreator($data['creator']);
		}
			if (isset($data['position'])) {
				$campaign->setPosition($data['position']);
			}
			if (array_key_exists('epilogue', $data)) {
				$campaign->setEpilogue($data['epilogue']);
			}

		// Only persist the static Campaign definition here. Per-team/runtime values
		// (campaignlist rows) should not be created at initialization and will be
		// created later via the UI. Return only the Campaign entity for import.
		$result[] = $campaign;
	}

	return $result;
}

	protected function importTaboosJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$taboosData = $this->getDataFromFile($fileinfo);
		foreach($taboosData as $tabooData) {
			$tabooData['cards'] = json_encode($tabooData['cards']);
			$taboo = $this->getEntityFromData('AppBundle\Entity\Taboo', $tabooData, [
					'code',
					'name',
					'date_start',
					'active',
					'cards'
			], [], []);
			if($taboo) {
				$result[] = $taboo;
				$this->em->persist($taboo);
			}
		}

		return $result;
	}


	protected function importPacksJsonFile(\SplFileInfo $fileinfo)
	{
		$result = [];

		$packsData = $this->getDataFromFile($fileinfo);
		foreach($packsData as $packData) {
			$pack = $this->getEntityFromData('AppBundle\Entity\Pack', $packData, [
					'code',
					'name',
					'position',
					'size',
					'date_release'
			], [
				'pack_type_code'
			], [
					'cgdb_id',
					'creator',
					'status',
					'theme',
					'visibility',
					'language',
					'environment'
			]);
			if($pack) {
				
				$result[] = $pack;
				$this->em->persist($pack);
			}
		}

		return $result;
	}

	protected function importCardsFromJsonData($cardsData) {
		$result = [];

		foreach($cardsData as $cardData) {
			// If the JSON doesn't include a subtype but the card belongs to a pack
			// whose pack type is 'nemesis', set subtype_code to 'nemesis' here
			// so the created Card entity gets the proper subtype link.
			// If the JSON doesn't include a subtype, and the card's set (not pack)
			// is of CardSetType 'nemesis', set subtype_code to 'nemesis' so the
			// created Card entity gets the proper subtype link.
			if ((empty($cardData['subtype_code']) || $cardData['subtype_code'] === null)
				&& !empty($cardData['set_code'])
				&& array_key_exists('Cardset', $this->collections)
				&& array_key_exists($cardData['set_code'], $this->collections['Cardset'])) {
				$cardset = $this->collections['Cardset'][$cardData['set_code']];
				if ($cardset && $cardset->getCardSetType() && $cardset->getCardSetType()->getCode() === 'nemesis') {
					$cardData['subtype_code'] = 'nemesis';
				}
			}
			$card = $this->getEntityFromData('AppBundle\Entity\Card', $cardData, [
				'code',
				'position',
				'quantity',
				'name'
			], [
				'faction_code',
				'faction2_code',
				'pack_code',
				'type_code',
				'subtype_code',
				'set_code',
				'back_card_code',
				'front_card_code'
			], [
				'deck_limit',
				'set_position',
				'illustrator',
				'flavor',
				'traits',
				'text',
				'cost',
				'cost_per_hero',
				'resource_physical',
				'resource_mental',
				'resource_energy',
				'resource_wild',
				'restrictions',
				'deck_options',
				'deck_requirements',
				'meta',
				'subname',
				'back_text',
				'back_flavor',
				'back_name',
				'double_sided',
				'is_unique',
				'hidden',
				'permanent',
				'errata',
				'octgn_id'

			]);
			if($card) {
				if ($card->getName()){
					$card->setRealName($card->getName());
				}
				if ($card->getTraits()){
					$card->setRealTraits($card->getTraits());
				}
				if ($card->getText()){
					$card->setRealText($card->getText());
				}
				$result[] = $card;
				$this->em->persist($card);
				if (isset($cardData['back_link'])){
					// if we have back link, store the reference here
					$this->links[] = ['card_id'=> $card->getCode(), 'target_id'=> $cardData['back_link']];
				}
			}
		}

		return $result;
	}

	protected function importCardsJsonFile(\SplFileInfo $fileinfo, $special="")
	{
		$result = [];

		$code = $fileinfo->getBasename('.json');
		if (stristr($code, "_encounter") !== FALSE && $special){
			return $result;
		}
		$code = str_replace("_encounter", "", $code);

		$pack = $this->em->getRepository('AppBundle:Pack')->findOneBy(['code' => $code]);
		if(!$pack) throw new \Exception("Unable to find Pack [$code]");

		$cardsData = $this->getDataFromFile($fileinfo);
		$result = $this->importCardsFromJsonData($cardsData);
		// return all cards imported
		return $result;
	}

	protected function copyFieldValueToEntity($entity, $entityName, $fieldName, $newJsonValue)
	{
		$metadata = $this->em->getClassMetadata($entityName);
		$type = $metadata->fieldMappings[$fieldName]['type'];

		// new value, by default what json gave us is the correct typed value
		$newTypedValue = $newJsonValue;

		// current value, by default the json, serialized value is the same as what's in the entity
		$getter = 'get'.ucfirst($fieldName);
		$currentJsonValue = $currentTypedValue = $entity->$getter();

		// if the field is a data, the default assumptions above are wrong
		if(in_array($type, ['date', 'datetime'])) {
			if($newJsonValue !== null) {
				$newTypedValue = new \DateTime($newJsonValue);
			}
			if($currentTypedValue !== null) {
				switch($type) {
					case 'date': {
						$currentJsonValue = $currentTypedValue->format('Y-m-d');
						break;
					}
					case 'datetime': {
						$currentJsonValue = $currentTypedValue->format('Y-m-d H:i:s');
					}
				}
			}
		}

		$different = ($currentJsonValue !== $newJsonValue);
		if($different) {
			//print_r(gettype($currentJsonValue));
			//print_r(gettype($newJsonValue));
			if (is_array($currentJsonValue) || is_array($newJsonValue)){
				$this->output->writeln("Changing the <info>$fieldName</info> of <info>".$entity->toString()."</info>");
			} else {
				$this->output->writeln("Changing the <info>$fieldName</info> of <info>".$entity->toString()."</info> ($currentJsonValue => $newJsonValue)");
			}
			$setter = 'set'.ucfirst($fieldName);
			$entity->$setter($newTypedValue);
		}
	}

	protected function copyKeyToEntity($entity, $entityName, $data, $key, $isMandatory = TRUE)
	{
		$metadata = $this->em->getClassMetadata($entityName);
		if(!key_exists($key, $data)) {
			if($isMandatory) {
				throw new \Exception("Missing key [$key] in ".json_encode($data));
			} else {
				$data[$key] = null;
			}
		}

		$value = $data[$key];
		if ($key == "is_unique"){
			if (!$value){
				$value = false;
			}
		}
		if ($key == "hidden"){
			if (!$value){
				$value = false;
			}
		}
		if ($key == "permanent"){
			if (!$value){
				$value = false;
			}
		}

		if ($key == "deck_requirements"){
			if ($value){
				$value = json_encode($value);
			}
		}

		// If environment is present but empty in JSON, do not overwrite existing value
		if ($key === 'environment' && ($value === null || $value === '')) {
			// treat empty environment in JSON as "no change"
			return;
		}

		if ($key == "meta"){
			if ($value){
				$value = json_encode($value);
			}
		}

		if ($key == "deck_options" && $value){
			if ($value){
				$value = json_encode($value);
			}
		}

		if(!key_exists($key, $metadata->fieldNames)) {
			throw new \Exception("Missing column [$key] in entity ".$entityName);
		}
		$fieldName = $metadata->fieldNames[$key];

		$this->copyFieldValueToEntity($entity, $entityName, $fieldName, $value);
	}

	protected function getEntityFromData($entityName, $data, $mandatoryKeys, $foreignKeys, $optionalKeys)
	{
		if(!key_exists('code', $data)) {
			throw new \Exception("Missing key [code] in ".json_encode($data));
		}

		if (key_exists('duplicate_of', $data) && !key_exists('name', $data)) {
			$this->duplicates[] = ['card' => $data, 'duplicate_of' => $data['duplicate_of']];
			return;
		}
		$entity = $this->em->getRepository($entityName)->findOneBy(['code' => $data['code']]);

		if(!$entity) {
			// if we cant find it, try more complex methods just to check
			// the only time this should work is if the existing name also has an _ meaning it was temporary.

			if (!$entity){
				$entity = new $entityName();
			}
		}
		$orig = $entity->serialize();
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($entity, $entityName, $data, $key, TRUE);
		}

		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($entity, $entityName, $data, $key, FALSE);
		}

		foreach($foreignKeys as $key) {
			$foreignEntityShortName = ucfirst(str_replace('_code', '', $key));
			if ($key === "front_card_code"){
				$foreignEntityShortName = "Card";
			}
			if ($key === "set_code") {
				$foreignEntityShortName = "Cardset";
			}
			if ($key === "pack_type_code") {
				$foreignEntityShortName = 'Packtype';
			}
			if ($key === "card_set_type_code") {
				$foreignEntityShortName = 'Cardsettype';
			}

			if(!key_exists($key, $data)) {
				// optional links to other tables
				if ($key === "faction2_code" || $key === "subtype_code" || $key === "set_code" || $key === "back_card_code" || $key === "front_card_code"){
					continue;
				}
				throw new \Exception("Missing key [$key] in ".json_encode($data));
			}

			$foreignCode = $data[$key];
			if(!key_exists($foreignEntityShortName, $this->collections)) {
				throw new \Exception("No collection for [$foreignEntityShortName] in ".json_encode($data));
			}

			if (!$foreignCode){
				continue;
			}
			//echo "\n";
			//print("hvor mange ".count($this->collections[$foreignEntityShortName]));
			if(!key_exists($foreignCode, $this->collections[$foreignEntityShortName])) {
				throw new \Exception("Invalid code [$foreignCode] for key [$key] in ".json_encode($data));
			}
			$foreignEntity = $this->collections[$foreignEntityShortName][$foreignCode];

			$getter = 'get'.$foreignEntityShortName;

			if(!$entity->$getter() || $entity->$getter()->getId() !== $foreignEntity->getId()) {
				$this->output->writeln("Changing the <info>$key</info> of <info>".$entity->toString()."</info>");
				$setter = 'set'.$foreignEntityShortName;
				$entity->$setter($foreignEntity);
			}
		}

		// special case for Card
		if($entityName === 'AppBundle\Entity\Card') {
			// calling a function whose name depends on the type_code
			$cleanName = $entity->getType()->getName();
			if ($cleanName == "Alter-Ego") {
				$cleanName = "AlterEgo";
			}
			// Support for player minion type name
			if ($cleanName == "Player Minion") {
				$cleanName = "PlayerMinion";
			}
			// Support for Modular Sets type name with a space
			if ($cleanName == "Modular Sets") {
				$cleanName = "ModularSets";
			}
			if ($cleanName == "Side Scheme") {
				$cleanName = "SideScheme";
			}
			if ($cleanName == "Main Scheme") {
				$cleanName = "MainScheme";
			}
			if ($cleanName == "Player Side Scheme") {
				$cleanName = "PlayerSideScheme";
			}
			if ($cleanName == "Evidence - Means") {
				$cleanName = "EvidenceMeans";
			}
			if ($cleanName == "Evidence - Motive") {
				$cleanName = "EvidenceMotive";
			}
			if ($cleanName == "Evidence - Opportunity") {
				$cleanName = "EvidenceOpportunity";
			}
			// Normalize the cleaned name: remove non-alphanumeric characters and CamelCase each part
			$parts = preg_split('/[^A-Za-z0-9]+/', $cleanName);
			$cleanName = '';
			foreach ($parts as $p) {
				if ($p !== '') $cleanName .= ucfirst($p);
			}            
			$functionName = 'import' . $cleanName . 'Data';
			// Only call the method if it exists to avoid fatal errors for unexpected types
			if (method_exists($this, $functionName)) {
				$this->$functionName($entity, $data);
			} else {
				$this->output->writeln("<comment>No importer found for type '".$entity->getType()->getName()."' (tried $functionName). Skipping specialized import.</comment>");
			}
		}

		if($entity->serialize() !== $orig || (isset($data['back_link']) && (!$entity->getLinkedTo() || $entity->getLinkedTo()->getCode() != $data['back_link']) )) return $entity;

	}

	protected function importSupportData(Card $card, $data)
	{

	}

	protected function importUpgradeData(Card $card, $data)
	{
		$optionalKeys = [
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importObligationData(Card $card, $data)
	{
		$optionalKeys = [
			'boost',
			'boost_star',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importHeroData(Card $card, $data)
	{
		$mandatoryKeys = [
			'attack',
			'defense',
			'hand_size',
			'health',
			'thwart',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'attack_star',
			'defense_star',
			'health_star',
			'scheme_acceleration',
			'thwart_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importAlterEgoData(Card $card, $data)
	{
		$mandatoryKeys = [
			'health',
			'hand_size',
			'recover',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'health_star',
			'recover_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importAllyData(Card $card, $data)
	{
		$mandatoryKeys = [
			'attack',
			'health',
			'thwart',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'attack_cost',
			'attack_star',
			'health_star',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_hazard',
			'thwart_cost',
			'thwart_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}


	protected function importMinionData(Card $card, $data)
	{
		$optionalKeys = [
			'attack',
			'attack_star',
			'boost',
			'boost_star',
			'health',
			'health_per_group',
			'health_per_hero',
			'health_star',
			'scheme',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_hazard',
			'scheme_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	/**
	 * Player Minion uses the same fields as Minion; wrapper to reuse the same logic
	 */
	protected function importPlayerMinionData(Card $card, $data)
	{
		// Delegate to the same handler as Minion
		$this->importMinionData($card, $data);
	}

	protected function importEnvironmentData(Card $card, $data)
	{
		$optionalKeys = [
			'boost',
			'boost_star',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_hazard',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importEvidenceMeansData(Card $card, $data)
	{

	}

	protected function importEvidenceMotiveData(Card $card, $data)
	{

	}

	protected function importEvidenceOpportunityData(Card $card, $data)
	{

	}

	/**
	 * Challenge cards don't need extra fields beyond the common ones.
	 * Provide a no-op importer to avoid undefined method errors when a
	 * card type named "Challenge" is encountered.
	 */
	protected function importChallengeData(Card $card, $data)
	{
		$optionalKeys = [
			'expansions_needed'
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	/**
	 * Modular Sets don't have special fields beyond common ones; provide a stub
	 * to avoid undefined method errors when the type name contains a space.
	 */
	protected function importModularSetsData(Card $card, $data)
	{
		// no-op
	}

	protected function importSideSchemeData(Card $card, $data)
	{
		$optionalKeys = [
			'base_threat',
			'base_threat_fixed',
			'base_threat_per_group',
			'boost',
			'boost_star',
			'escalation_threat',
			'escalation_threat_fixed',
			'escalation_threat_star',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importMainSchemeData(Card $card, $data)
	{
		$optionalKeys = [
			'base_threat',
			'base_threat_fixed',
			'base_threat_per_group',
			'escalation_threat',
			'escalation_threat_fixed',
			'escalation_threat_star',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
			'stage',
			'threat',
			'threat_fixed',
			'threat_per_group',
			'threat_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importPlayerSideSchemeData(Card $card, $data)
	{
		$mandatoryKeys = [
			'base_threat',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'base_threat_fixed',
			'base_threat_per_group',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importEventData(Card $card, $data)
	{
		$mandatoryKeys = [
			'cost'
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}
	}

	protected function importResourceData(Card $card, $data)
	{

	}

	protected function importVillainData(Card $card, $data)
	{
		$mandatoryKeys = [
			'health',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'attack',
			'attack_star',
			'health_per_group',
			'health_per_hero',
			'health_star',
			'scheme',
			'scheme_star',
			'stage',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importLeaderData(Card $card, $data)
	{
		$mandatoryKeys = [
			'health',
		];
		foreach($mandatoryKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, TRUE);
		}

		$optionalKeys = [
			'attack',
			'attack_star',
			'health_per_hero',
			'health_star',
			'scheme',
			'scheme_star',
			'stage',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importTreacheryData(Card $card, $data)
	{
		$optionalKeys = [
			'boost',
			'boost_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function importAttachmentData(Card $card, $data)
	{
		$optionalKeys = [
			'attack',
			'attack_star',
			'boost',
			'boost_star',
			'scheme',
			'scheme_acceleration',
			'scheme_amplify',
			'scheme_crisis',
			'scheme_hazard',
			'scheme_star',
		];
		foreach($optionalKeys as $key) {
			$this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, FALSE);
		}
	}

	protected function getDataFromFile(\SplFileInfo $fileinfo)
	{

		$file = $fileinfo->openFile('r');
		$file->setFlags(\SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

		$lines = [];
		foreach($file as $line) {
			if($line !== false) $lines[] = $line;
		}
		$content = implode('', $lines);

		// Strip UTF-8 BOM if present (prevents json_decode syntax errors)
		if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
			$content = substr($content, 3);
		}

		$data = json_decode($content, true);

		if($data === null) {
			throw new \Exception("File [".$fileinfo->getPathname()."] contains incorrect JSON (error code ".json_last_error().")");
		}

		return $data;
	}

	protected function getDataFromString($string) {
		$data = json_decode($string, true);

		if($data === null) {
			throw new \Exception("String contains incorrect JSON (error code ".json_last_error().")");
		}

		return $data;
	}

	protected function getFileInfo($path, $filename)
	{
		$fs = new Filesystem();

		if(!$fs->exists($path)) {
			throw new \Exception("No repository found at [$path]");
		}

		$filepath = "$path/$filename";

		if(!$fs->exists($filepath)) {
			throw new \Exception("No $filename file found at [$path]");
		}

		return new \SplFileInfo($filepath);
	}

	protected function getFileSystemIterator($path)
	{
		$fs = new Filesystem();

		if(!$fs->exists($path)) {
			throw new \Exception("No repository found at [$path]");
		}

		$iterator = new \GlobIterator("$path/*.json");

		if(!$iterator->count()) {
			throw new \Exception("No json file found at [$path]");
		}

		return $iterator;
	}

	protected function loadCollection($entityShortName)
	{
		$this->collections[$entityShortName] = [];
		$entities = $this->em->getRepository('AppBundle:'.$entityShortName)->findAll();
		//echo $entityShortName."\n";
		foreach($entities as $entity) {
			$this->collections[$entityShortName][$entity->getCode()] = $entity;
			//echo $entity->getCode()."\n";
		}
	}

}
