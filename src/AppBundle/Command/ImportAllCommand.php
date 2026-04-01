<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Card;

/**
 * Full non-interactive data import command, equivalent to app:import:std
 * but without interactive confirmation prompts and without JSON file generation.
 *
 * Usage:
 *   php bin/console app:import:all ../../marvelsdb_fanmade_data
 *   php bin/console app:import:all ../../marvelsdb_fanmade_data --dry-run
 *   php bin/console app:import:all ../../marvelsdb_fanmade_data --skip-scenario
 *   php bin/console app:import:all ../../marvelsdb_fanmade_data --player
 */
class ImportAllCommand extends ContainerAwareCommand
{
    /** @var EntityManager */
    private $em;

    private $links = [];
    private $duplicates = [];

    /** @var OutputInterface */
    private $output;

    private $collections = [];

    private $dryRun = false;

    protected function configure()
    {
        $this
            ->setName('app:import:all')
            ->setDescription('Full non-interactive import (factions, types, packs, cards, scenarios, campaigns) — no JSON generation')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the data repository (e.g. ../../marvelsdb_fanmade_data)'
            )
            ->addOption(
                'player',
                null,
                InputOption::VALUE_NONE,
                'Only player cards'
            )
            ->addOption(
                'skip-scenario',
                null,
                InputOption::VALUE_NONE,
                'Skip importing scenarios (scenario.json)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Parse files and report counts without writing to the database'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path         = $input->getArgument('path');
        $playerOnly   = $input->getOption('player');
        $skipScenario = $input->getOption('skip-scenario');
        $isDryRun     = $input->getOption('dry-run');
        $this->dryRun = $isDryRun;

        $this->em     = $this->getContainer()->get('doctrine')->getEntityManager();
        $this->output = $output;

        $output->writeln("Repository : <info>{$path}</info>");
        if ($isDryRun) {
            $output->writeln("<comment>Dry-run mode — no data will be written.</comment>");
        }
        $output->writeln('');

        // --- Factions ---
        $this->runStep($output, $isDryRun, 'Classes (factions)', function () use ($path) {
            return $this->importFactionsJsonFile($this->getFileInfo($path, 'factions.json'));
        });
        $this->loadCollection('Faction');
        $this->collections['Faction2'] = $this->collections['Faction'];

        $this->runStep($output, $isDryRun, 'Fan-made Classes (factions)', function () use ($path) {
            return $this->importFactionsJsonFile($this->getFileInfo($path, 'factions_fanmade.json'));
        });
        $this->loadCollection('Faction');
        $this->collections['Faction2'] = $this->collections['Faction'];

        // --- Types ---
        $this->runStep($output, $isDryRun, 'Types', function () use ($path) {
            return $this->importTypesJsonFile($this->getFileInfo($path, 'types.json'));
        });
        $this->loadCollection('Type');

        $this->runStep($output, $isDryRun, 'Fan-made Types', function () use ($path) {
            return $this->importTypesJsonFile($this->getFileInfo($path, 'types_fanmade.json'));
        });
        $this->loadCollection('Type');

        // --- Subtypes ---
        $this->runStep($output, $isDryRun, 'SubTypes', function () use ($path) {
            return $this->importSubtypesJsonFile($this->getFileInfo($path, 'subtypes.json'));
        });
        $this->loadCollection('Subtype');

        // --- PackTypes ---
        $this->runStep($output, $isDryRun, 'PackTypes', function () use ($path) {
            return $this->importPacktypesJsonFile($this->getFileInfo($path, 'packtypes.json'));
        });
        $this->loadCollection('Packtype');

        $this->runStep($output, $isDryRun, 'Fan-made PackTypes', function () use ($path) {
            return $this->importPacktypesJsonFile($this->getFileInfo($path, 'packtypes_fanmade.json'));
        });
        $this->loadCollection('Packtype');

        // --- CardsetTypes ---
        $this->runStep($output, $isDryRun, 'CardsetTypes', function () use ($path) {
            return $this->importCardsettypesJsonFile($this->getFileInfo($path, 'settypes.json'));
        });
        $this->loadCollection('Cardsettype');

        // Scenarios import moved later (just before Campaigns)

        // --- Fan-made CardsetTypes ---
        $this->runStep($output, $isDryRun, 'Fan-made CardsetTypes', function () use ($path) {
            return $this->importCardsettypesJsonFile($this->getFileInfo($path, 'settypes_fanmade.json'));
        });
        $this->loadCollection('Cardsettype');

        // --- Card Sets ---
        $this->runStep($output, $isDryRun, 'Card Sets', function () use ($path) {
            return $this->importCardSetsJsonFile($this->getFileInfo($path, 'sets.json'));
        });
        $this->loadCollection('Cardset');

        $this->runStep($output, $isDryRun, 'Fan-made Card Sets', function () use ($path) {
            return $this->importCardSetsJsonFile($this->getFileInfo($path, 'sets_fanmade.json'));
        });
        $this->loadCollection('Cardset');

        // --- Packs ---
        $this->runStep($output, $isDryRun, 'Packs', function () use ($path) {
            return $this->importPacksJsonFile($this->getFileInfo($path, 'packs.json'));
        });
        $this->loadCollection('Pack');

        $this->runStep($output, $isDryRun, 'Fan-made Packs', function () use ($path) {
            return $this->importPacksJsonFile($this->getFileInfo($path, 'packs_fanmade.json'));
        });
        $this->loadCollection('Pack');

        // --- Cards ---
        $output->writeln("Importing <info>Cards</info>…");
        if (!$isDryRun) {
            $imported = [];
            $fileSystemIterator = $this->getFileSystemIterator($path . '/pack/');
            foreach ($fileSystemIterator as $fileinfo) {
                $imported = array_merge($imported, $this->importCardsJsonFile($fileinfo, $playerOnly));
            }
            $this->em->flush();
            $output->writeln(sprintf("  <info>%d</info> card(s) processed.", count($imported)));
            if ($output->isVerbose()) {
                foreach ($imported as $card) {
                    $output->writeln("    <fg=yellow>[updated]</> " . $card->getCode() . " " . $card->getName());
                }
            }

            // Resolve back-links
            if ($this->links && count($this->links) > 0) {
                $output->writeln(sprintf("  Resolving <info>%d</info> link(s)…", count($this->links)));
                $this->loadCollection('Card');
                foreach ($this->links as $link) {
                    $card   = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $link['card_id']]);
                    $target = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $link['target_id']]);
                    if ($card && $target) {
                        $card->setLinkedTo($target);
                        $target->setLinkedTo();
                        $output->writeln(sprintf("    Link: <info>%s</info> ↔ <info>%s</info>", $card->getName(), $target->getName()));
                    }
                }
                $this->em->flush();
            }

            // Resolve duplicates
            if ($this->duplicates && count($this->duplicates) > 0) {
                $output->writeln(sprintf("  Resolving <info>%d</info> duplicate(s)…", count($this->duplicates)));
                $this->loadCollection('Card');
                foreach ($this->duplicates as $duplicate) {
                    $duplicateOf = $this->em->getRepository('AppBundle\\Entity\\Card')->findOneBy(['code' => $duplicate['duplicate_of']]);
                    $newCard     = $duplicate['card'];
                    $newCardData = $duplicateOf->serialize();
                    $newCardData['code']         = $newCard['code'];
                    $newCardData['duplicate_of'] = $duplicate['duplicate_of'];
                    if (isset($newCard['pack_code']))  $newCardData['pack_code']  = $newCard['pack_code'];
                    if (isset($newCard['position']))   $newCardData['position']   = $newCard['position'];
                    if (isset($newCard['quantity']))   $newCardData['quantity']   = $newCard['quantity'];
                    if (isset($newCard['flavor']))     $newCardData['flavor']     = $newCard['flavor'];
                    if (array_key_exists('alt_art', $newCard))    $newCardData['alt_art'] = $newCard['alt_art'];
                    elseif (array_key_exists('alt-art', $newCard)) $newCardData['alt_art'] = $newCard['alt-art'];
                    elseif (array_key_exists('altArt', $newCard))  $newCardData['alt_art'] = $newCard['altArt'];
                    $duplicatesAdded = $this->importCardsFromJsonData([$newCardData]);
                    if ($duplicatesAdded && isset($duplicatesAdded[0])) {
                        $duplicatesAdded[0]->setDuplicateOf($duplicateOf);
                    }
                }
                $this->em->flush();
            }
        } else {
            $fileSystemIterator = $this->getFileSystemIterator($path . '/pack/');
            $compareFields = ['name', 'text', 'traits'];
            $total = 0; $create = 0; $update = 0; $dupTotal = 0; $linkTotal = 0;
            $fieldCounts = array_fill_keys($compareFields, 0);
            $allDetails  = [];
            foreach ($fileSystemIterator as $fileinfo) {
                try {
                    $cardsData  = $this->getDataFromFile($fileinfo);
                    $fileDetails = [];
                    $counts = $this->dryRunCount($cardsData, 'AppBundle\\Entity\\Card', $compareFields, $fileDetails);
                    $total    += $counts['total'];
                    $create   += $counts['create'];
                    $update   += $counts['update'];
                    $dupTotal += $counts['duplicates'] ?? 0;
                    $linkTotal += $counts['links'] ?? 0;
                    foreach ($compareFields as $f) {
                        $fieldCounts[$f] += $counts['fieldCounts'][$f] ?? 0;
                    }
                    $allDetails = array_merge($allDetails, $fileDetails);
                } catch (\Exception $e) {}
            }
            $unchanged = $total - $create - $update;
            $parts = [];
            foreach ($fieldCounts as $f => $n) {
                if ($n > 0) $parts[] = "{$f}: {$n}";
            }
            $updateDetail = $parts ? ' (' . implode(', ', $parts) . ')' : '';
            $output->writeln(sprintf(
                "  <comment>Dry-run: %d total — %d to create, %d to update%s, %d unchanged.</comment>",
                $total, $create, $update, $updateDetail, $unchanged
            ));
            if ($dupTotal > 0) {
                $output->writeln("  <comment>  + {$dupTotal} duplicate(s) to rebuild, {$linkTotal} back-link(s) to resolve.</comment>");
            }
            if ($output->isVerbose()) {
                foreach ($allDetails as $d) {
                    if ($d['action'] === 'create') {
                        $output->writeln("    <fg=green>[create]</> {$d['code']}");
                    } else {
                        $cur = mb_substr((string)$d['current'],  0, 60);
                        $inc = mb_substr((string)$d['incoming'], 0, 60);
                        $output->writeln("    <fg=yellow>[update]</> {$d['code']} <comment>{$d['field']}</comment>: '{$cur}' → '{$inc}'");
                    }
                }
            }
        }

        // --- Scenarios ---
        if (!$skipScenario) {
            $this->runStep($output, $isDryRun, 'Scenarios', function () use ($path) {
                return $this->importScenariosJsonFile($this->getFileInfo($path, 'scenario.json'));
            });
            $this->loadCollection('Scenario');
            // Reload collections potentially detached by scenario batch flushes
            $this->loadCollection('Faction');
            $this->collections['Faction2'] = $this->collections['Faction'];
            $this->loadCollection('Type');
            $this->loadCollection('Subtype');
            $this->loadCollection('Packtype');
        } else {
            $output->writeln("<comment>Skipping Scenarios import (--skip-scenario).</comment>");
        }

        // --- Campaigns ---
        $output->writeln("Importing <info>Campaigns</info>…");
        try {
            $campaignsFile = $this->getFileInfo($path, 'campaigns.json');
            if ($isDryRun) {
                $raw     = $this->getDataFromFile($campaignsFile);
                $baseDir = dirname($campaignsFile->getPathname()) . DIRECTORY_SEPARATOR . 'campaign';
                // campaigns.json may be a list of code strings — resolve each to its campaign JSON file
                $entries = [];
                if (is_array($raw) && count($raw) > 0 && is_string(reset($raw))) {
                    foreach ($raw as $code) {
                        $campaignFile = $baseDir . DIRECTORY_SEPARATOR . $code . '.json';
                        if (file_exists($campaignFile)) {
                            $decoded = json_decode(file_get_contents($campaignFile), true);
                            if ($decoded !== null) {
                                $entries[] = $decoded;
                            }
                        }
                    }
                } else {
                    $entries = $raw;
                }
                $total  = count($entries);
                $create = 0; $update = 0;
                $repo   = $this->em->getRepository('AppBundle:Campaign');
                foreach ($entries as $entry) {
                    $code = isset($entry['code']) ? $entry['code'] : null;
                    $name = isset($entry['name']) ? $entry['name'] : null;
                    $existing = ($code ? $repo->findOneBy(['code' => $code]) : null)
                             ?: ($name ? $repo->findOneBy(['name' => $name]) : null);
                    if (!$existing) {
                        $create++;
                    } else {
                        $needsUpdate = false;
                        foreach (['name', 'size', 'creator', 'position'] as $f) {
                            $getter = 'get' . ucfirst($f);
                            if (!method_exists($existing, $getter)) continue;
                            $incoming = isset($entry[$f]) ? (string)$entry[$f] : null;
                            if ((string)$existing->$getter() !== (string)$incoming) { $needsUpdate = true; break; }
                        }
                        if ($needsUpdate) $update++;
                    }
                }
                $unchanged = $total - $create - $update;
                $output->writeln(sprintf(
                    "  <comment>Dry-run: %d total — %d to create, %d to update, %d unchanged.</comment>",
                    $total, $create, $update, $unchanged
                ));
            } else {
                $importedCampaigns = $this->importCampaignlistsJsonFile($campaignsFile);
                $output->writeln(sprintf("  <info>%d</info> campaign(s) processed.", count($importedCampaigns)));
                if (count($importedCampaigns) > 0) {
                    foreach ($importedCampaigns as $c) {
                        $this->em->persist($c);
                    }
                    $this->em->flush();
                }
            }
        } catch (\Exception $e) {
            $output->writeln("  <comment>Skipped: " . $e->getMessage() . "</comment>");
        }

        $output->writeln('');
        $output->writeln("<info>All imports complete.</info>");

        return 0;
    }

    /**
     * Runs a single import step: logs the label, calls the closure, flushes, logs count.
     */
    private function runStep(OutputInterface $output, bool $isDryRun, string $label, \Closure $fn): void
    {
        $output->writeln("Importing <info>{$label}</info>…");
        try {
            $imported = $fn();
            if ($isDryRun) {
                if (is_array($imported) && array_key_exists('create', $imported)) {
                    $unchanged = $imported['total'] - $imported['create'] - $imported['update'];
                    $updateDetail = '';
                    if (!empty($imported['fieldCounts'])) {
                        $parts = [];
                        foreach ($imported['fieldCounts'] as $f => $n) {
                            if ($n > 0) $parts[] = "{$f}: {$n}";
                        }
                        if ($parts) $updateDetail = ' (' . implode(', ', $parts) . ')';
                    }
                    $output->writeln(sprintf(
                        "  <comment>Dry-run: %d total — %d to create, %d to update%s, %d unchanged.</comment>",
                        $imported['total'],
                        $imported['create'],
                        $imported['update'],
                        $updateDetail,
                        $unchanged
                    ));
                    if ($output->isVerbose() && !empty($imported['details'])) {
                        foreach ($imported['details'] as $d) {
                            if ($d['action'] === 'create') {
                                $output->writeln("    <fg=green>[create]</> {$d['code']}");
                            } else {
                                $cur = mb_substr((string)$d['current'],  0, 60);
                                $inc = mb_substr((string)$d['incoming'], 0, 60);
                                $output->writeln("    <fg=yellow>[update]</> {$d['code']} <comment>{$d['field']}</comment>: '{$cur}' → '{$inc}'");
                            }
                        }
                    }
                } else {
                    $count = is_array($imported) ? count($imported) : 0;
                    $output->writeln(sprintf("  <comment>Dry-run: %d item(s) parsed.</comment>", $count));
                }
            } else {
                $this->em->flush();
                $count = is_array($imported) ? count($imported) : 0;
                $output->writeln(sprintf("  <info>%d</info> item(s) processed.", $count));
                if ($output->isVerbose() && is_array($imported) && $count > 0) {
                    foreach ($imported as $entity) {
                        $code = method_exists($entity, 'getCode') ? $entity->getCode() : '';
                        $name = method_exists($entity, 'getName') ? $entity->getName() : '';
                        $output->writeln("    <fg=yellow>[updated]</> {$code} {$name}");
                    }
                }
            }
        } catch (\Exception $e) {
            $output->writeln("  <error>Error: " . $e->getMessage() . "</error>");
        }
    }

    /**
     * Counts how many items in $list would be created, really updated (changed fields),
     * or left unchanged. DB reads only — no writes.
     * Returns ['total' => N, 'create' => N, 'update' => N].
     *
     * @param array  $compareFields  Getter-derivable field names to compare (e.g. ['name', 'position']).
     *                               For date fields the value is compared as 'Y-m-d'.
     */
    private function dryRunCount(array $list, string $entityClass, array $compareFields = ['name'], array &$details = []): array
    {
        $total       = 0;
        $create      = 0;
        $update      = 0;
        $fieldCounts = array_fill_keys($compareFields, 0);
        $duplicateCount = 0;
        $linkCount      = 0;
        foreach ($list as $data) {
            if (empty($data['code'])) continue;
            // Duplicate cards have no 'name' — they are rebuilt from the original at import time.
            if (array_key_exists('duplicate_of', $data) && !array_key_exists('name', $data)) {
                $duplicateCount++;
                continue;
            }
            if (isset($data['back_link'])) $linkCount++;
            $total++;
            $exists = $this->em->getRepository($entityClass)->findOneBy(['code' => $data['code']]);
            if (!$exists) {
                $create++;
                $details[] = ['action' => 'create', 'code' => $data['code'], 'field' => null, 'current' => null, 'incoming' => null];
            } else {
                $changedField   = null;
                $changedCurrent  = null;
                $changedIncoming = null;
                foreach ($compareFields as $field) {
                    $getter = 'get' . ucfirst($field);
                    if (!method_exists($exists, $getter)) continue;
                    $current = $exists->$getter();
                    if ($current instanceof \DateTime) {
                        $current = $current->format('Y-m-d');
                    }
                    // JSON keys may be snake_case (e.g. is_primary) while compareFields use camelCase (isPrimary)
                    $snakeKey = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($field)));
                    if (array_key_exists($field, $data)) {
                        $incoming = $data[$field];
                    } elseif (array_key_exists($snakeKey, $data)) {
                        $incoming = $data[$snakeKey];
                    } else {
                        $incoming = null;
                    }
                    if ((string)$current !== (string)$incoming) {
                        $changedField    = $field;
                        $changedCurrent  = $current;
                        $changedIncoming = $incoming;
                        break;
                    }
                }
                if ($changedField !== null) {
                    $update++;
                    $fieldCounts[$changedField]++;
                    $details[] = ['action' => 'update', 'code' => $data['code'], 'field' => $changedField, 'current' => $changedCurrent, 'incoming' => $changedIncoming];
                }
            }
        }
        return ['total' => $total, 'create' => $create, 'update' => $update, 'fieldCounts' => $fieldCounts, 'details' => $details, 'duplicates' => $duplicateCount, 'links' => $linkCount];
    }

    // -------------------------------------------------------------------------
    // Import methods
    // -------------------------------------------------------------------------

    protected function importFactionsJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Faction', ['name', 'isPrimary']);
        }
        $result = [];
        foreach ($list as $data) {
            $faction = $this->getEntityFromData('AppBundle\\Entity\\Faction', $data, [
                'code',
                'name',
                'is_primary',
            ], [], []);
            if ($faction) {
                $result[] = $faction;
                $this->em->persist($faction);
            }
        }
        return $result;
    }

    protected function importTypesJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Type', ['name']);
        }
        $result = [];
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Type', $data, [
                'code',
                'name',
            ], [], []);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importSubtypesJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Subtype', ['name']);
        }
        $result = [];
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Subtype', $data, [
                'code',
                'name',
            ], [], []);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importPacktypesJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Packtype', ['name']);
        }
        $result = [];
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Packtype', $data, [
                'code',
                'name',
            ], [], []);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importCardsettypesJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Cardsettype', ['name']);
        }
        $result = [];
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Cardsettype', $data, [
                'code',
                'name',
            ], [], []);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importCardSetsJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Cardset', ['name', 'creator', 'status']);
        }
        $result = [];
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Cardset', $data, [
                'code',
                'name',
            ], [
                'card_set_type_code',
            ], [
                'parent_code',
                'creator',
                'status',
            ]);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importScenariosJsonFile(\SplFileInfo $fileinfo)
    {
        $list = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($list, 'AppBundle\\Entity\\Scenario', ['title', 'villainSetCode', 'difficulty', 'creator']);
        }
        $result = [];

        $existingCodes = [];
        try {
            $rows = $this->em->getConnection()->fetchAll('SELECT code FROM scenario');
            $codes = [];
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (is_array($r)) {
                        if (isset($r['code'])) $codes[] = $r['code'];
                        else $codes[] = reset($r);
                    }
                }
            }
            if (is_array($codes)) $existingCodes = array_flip($codes);
        } catch (\Exception $e) {
            $existingCodes = [];
        }

        try {
            $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        } catch (\Exception $e) {}

        $batchSize = 50;
        $i = 0;

        foreach ($list as $data) {
            $code = null;
            if (isset($data['scenario_id']) && is_numeric($data['scenario_id'])) {
                $code = 'scenario-' . intval($data['scenario_id']);
            } else {
                $villain = isset($data['villain_set_code']) ? $data['villain_set_code'] : null;
                $title   = isset($data['title']) ? $data['title'] : null;
                $slug    = '';
                if ($title) {
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT', $title))));
                    $slug = trim($slug, '-');
                }
                $code = $villain ? ($villain . ($slug ? '-' . $slug : '')) : ($slug ?: null);
            }

            $baseCode = $code;
            $counter  = 1;
            $scenario = null;
            if ($code) {
                $scenario = $this->em->getRepository('AppBundle:Scenario')->findOneBy(['code' => $code]);
                if (!$scenario) {
                    while ($code && isset($existingCodes[$code])) {
                        $code = $baseCode . '-' . $counter;
                        $counter++;
                    }
                    if ($code) {
                        $scenario = $this->em->getRepository('AppBundle:Scenario')->findOneBy(['code' => $code]);
                    }
                }
            }
            $wasExisting = false;
            if (!$scenario) {
                $scenario = new \AppBundle\Entity\Scenario();
                if ($code) $scenario->setCode($code);
            } else {
                $wasExisting = true;
            }

            // capture original serialized state if available
            $orig = (method_exists($scenario, 'serialize')) ? $scenario->serialize() : null;

            if (isset($data['villain_set_code']))  $scenario->setVillainSetCode($data['villain_set_code']);
            if (isset($data['title']))              $scenario->setTitle($data['title']);
            if (isset($data['nbmodular']))          $scenario->setNbmodular($data['nbmodular']);
            if (isset($data['modular_set_codes']))  $scenario->setModularSetCodes(json_encode($data['modular_set_codes']));
            if (isset($data['difficulty']))         $scenario->setDifficulty($data['difficulty']);
            if (array_key_exists('text', $data))    $scenario->setText($data['text']);
            if (isset($data['creator']))            $scenario->setCreator($data['creator']);

            if (array_key_exists('visibility', $data)) {
                $vis = $data['visibility'];
                if (is_bool($vis)) {
                    $v = $vis;
                } elseif (is_numeric($vis)) {
                    $v = ((int) $vis) !== 0;
                } elseif (is_string($vis)) {
                    $lower = strtolower(trim($vis));
                    $v = ($lower === 'true' || $lower === '1' || $lower === 'yes');
                } else {
                    $v = (bool) $vis;
                }
                $scenario->setVisibility((bool) $v);
            } else {
                $scenario->setVisibility(false);
            }

            // Only persist/return scenarios that are new or whose serialized state changed
            $new = (method_exists($scenario, 'serialize')) ? $scenario->serialize() : null;
            if ($orig === null || $new !== $orig) {
                $result[] = $scenario;
                $this->em->persist($scenario);
                if ($code) $existingCodes[$code] = true;
            }

            $i++;
            if ($i % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {}

        return $result;
    }

    protected function importCampaignsJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];
        $list   = $this->getDataFromFile($fileinfo);
        foreach ($list as $data) {
            $type = $this->getEntityFromData('AppBundle\\Entity\\Campaign', $data, [
                'code',
                'name',
                'size',
            ], [], ['creator', 'position']);
            if ($type) {
                $result[] = $type;
                $this->em->persist($type);
            }
        }
        return $result;
    }

    protected function importCampaignlistsJsonFile(\SplFileInfo $fileinfo)
    {
        $result = [];
        $list   = $this->getDataFromFile($fileinfo);

        // Support codes-only list: each item is a campaign code string
        if (is_array($list) && count($list) > 0 && is_string(reset($list))) {
            $codes   = $list;
            $list    = [];
            $baseDir = dirname($fileinfo->getPathname()) . DIRECTORY_SEPARATOR . 'campaign';
            foreach ($codes as $code) {
                $campaignFile = $baseDir . DIRECTORY_SEPARATOR . $code . '.json';
                if (file_exists($campaignFile)) {
                    $raw   = file_get_contents($campaignFile);
                    $entry = json_decode($raw, true);
                    if ($entry !== null) {
                        $list[] = $entry;
                    } else {
                        if ($this->output) $this->output->writeln("  <comment>Warning: unable to decode {$campaignFile}</comment>");
                    }
                } else {
                    if ($this->output) $this->output->writeln("  <comment>Warning: campaign file not found: {$campaignFile}</comment>");
                }
            }
        }

        if ($this->output) {
            $this->output->writeln("  Read <comment>" . (is_array($list) ? count($list) : 0) . "</comment> campaign entries.");
        }

        foreach ($list as $data) {
            $repo     = $this->em->getRepository('AppBundle:Campaign');
            $existing = null;
            if (!empty($data['code'])) {
                $existing = $repo->findOneBy(['code' => $data['code']]);
            }
            if (!$existing && !empty($data['name'])) {
                $existing = $repo->findOneBy(['name' => $data['name']]);
            }

            if ($existing) {
                $orig = (method_exists($existing, 'serialize')) ? $existing->serialize() : null;
                if (isset($data['code']))     $existing->setCode($data['code']);
                if (isset($data['name']))     $existing->setName($data['name']);
                if (isset($data['size']))     $existing->setSize($data['size']);
                if (isset($data['type']))     $existing->setType($data['type']);
                if (isset($data['scenarios'])) {
                    $norm = $this->normalizeScenariosArray($data['scenarios']);
                    $existing->setScenarios(json_encode($norm));
                }
                if (isset($data['modulars'])) {
                    $existing->setModulars(json_encode($this->normalizeModularsArray($data['modulars'])));
                }
                $this->applyCampaignNotes($existing, $data);
                if (array_key_exists('description', $data)) $existing->setDescription($data['description']);
                if (array_key_exists('epilogue', $data))     $existing->setEpilogue($data['epilogue']);
                if (isset($data['image']))    $existing->setImage($data['image']);
                if (isset($data['creator']))  $existing->setCreator($data['creator']);
                if (isset($data['position'])) $existing->setPosition($data['position']);
                $new = (method_exists($existing, 'serialize')) ? $existing->serialize() : null;
                // Determine whether we should treat this existing campaign as changed.
                // If the entity provides a serialize() method, compare serialized forms.
                // If serialize() is not available, only treat it as changed when
                // the JSON includes explicit static fields to apply (avoid false-positives).
                $shouldPersist = false;
                if ($orig !== null) {
                    if ($new !== $orig) $shouldPersist = true;
                } else {
                    $compareKeys = ['code','name','size','type','scenarios','modulars','description','epilogue','image','creator','position'];
                    foreach ($compareKeys as $k) {
                        if (array_key_exists($k, $data)) { $shouldPersist = true; break; }
                    }
                }
                if ($shouldPersist) {
                    $result[] = $existing;
                    $this->em->persist($existing);
                }
                continue;
            }

            $campaign = new \AppBundle\Entity\Campaign();
            if (isset($data['code']))    $campaign->setCode($data['code']);
            if (isset($data['name']))    $campaign->setName($data['name']);
            if (isset($data['type']))    $campaign->setType($data['type']);
            if (isset($data['size']))    $campaign->setSize($data['size']);
            if (isset($data['scenarios']) && is_array($data['scenarios'])) {
                $campaign->setScenarios(json_encode($this->normalizeScenariosArray($data['scenarios'])));
            } else {
                $campaign->setScenarios(null);
            }
            if (isset($data['modulars']) && is_array($data['modulars'])) {
                $campaign->setModulars(json_encode($this->normalizeModularsArray($data['modulars'])));
            } else {
                $campaign->setModulars(null);
            }
            $this->applyCampaignNotes($campaign, $data);
            if (array_key_exists('description', $data)) $campaign->setDescription($data['description']);
            if (array_key_exists('epilogue', $data))     $campaign->setEpilogue($data['epilogue']);
            if (isset($data['image']))    $campaign->setImage($data['image']);
            if (isset($data['creator']))  $campaign->setCreator($data['creator']);
            if (isset($data['position'])) $campaign->setPosition($data['position']);
            $result[] = $campaign;
        }

        return $result;
    }

    private function normalizeScenariosArray(array $scenarios): array
    {
        $norm = [];
        foreach ($scenarios as $s) {
            if (!is_array($s)) continue;
            $entry = [];
            foreach (['code', 'name', 'description', 'introduction', 'resolution', 'image', 'epilogue'] as $k) {
                if (isset($s[$k])) $entry[$k] = $s[$k];
            }
            $norm[] = $entry;
        }
        return $norm;
    }

    private function normalizeModularsArray(array $modulars): array
    {
        $norm = [];
        foreach ($modulars as $scode => $mlist) {
            $norm[$scode] = [];
            if (!is_array($mlist)) continue;
            foreach ($mlist as $m) {
                if (is_string($m)) {
                    $norm[$scode][] = ['code' => $m, 'name' => null];
                } elseif (is_array($m)) {
                    if (isset($m['code']) || isset($m['name'])) {
                        $norm[$scode][] = [
                            'code' => isset($m['code']) ? $m['code'] : (isset($m[1]) ? $m[1] : null),
                            'name' => isset($m['name']) ? $m['name'] : (isset($m[0]) ? $m[0] : null),
                        ];
                    } else {
                        $norm[$scode][] = ['code' => isset($m[1]) ? $m[1] : null, 'name' => isset($m[0]) ? $m[0] : null];
                    }
                }
            }
        }
        return $norm;
    }

    private function applyCampaignNotes($campaign, array $data): void
    {
        $notesKey    = isset($data['campaign_notes'])    ? 'campaign_notes'    : (isset($data['scenario_notes'])    ? 'scenario_notes'    : null);
        $countersKey = isset($data['campaign_counters']) ? 'campaign_counters' : (isset($data['scenario_counters']) ? 'scenario_counters' : null);

        if ($notesKey) {
            if (method_exists($campaign, 'setCampaignNotes'))   $campaign->setCampaignNotes(json_encode($data[$notesKey]));
            elseif (method_exists($campaign, 'setScenarioNotes')) $campaign->setScenarioNotes(json_encode($data[$notesKey]));
        }
        if ($countersKey) {
            if (method_exists($campaign, 'setCampaignCounters'))   $campaign->setCampaignCounters(json_encode($data[$countersKey]));
            elseif (method_exists($campaign, 'setScenarioCounters')) $campaign->setScenarioCounters(json_encode($data[$countersKey]));
        }
        if (isset($data['campaign_checkbox']) && method_exists($campaign, 'setCampaignCheckbox')) {
            $campaign->setCampaignCheckbox(json_encode($data['campaign_checkbox']));
        }
        if (isset($data['tracks']) && method_exists($campaign, 'setTracks')) {
            $campaign->setTracks(json_encode($data['tracks']));
        }
    }

    protected function importTaboosJsonFile(\SplFileInfo $fileinfo)
    {
        $result     = [];
        $taboosData = $this->getDataFromFile($fileinfo);
        foreach ($taboosData as $tabooData) {
            $tabooData['cards'] = json_encode($tabooData['cards']);
            $taboo = $this->getEntityFromData('AppBundle\Entity\Taboo', $tabooData, [
                'code',
                'name',
                'date_start',
                'active',
                'cards',
            ], [], []);
            if ($taboo) {
                $result[] = $taboo;
                $this->em->persist($taboo);
            }
        }
        return $result;
    }

    protected function importPacksJsonFile(\SplFileInfo $fileinfo)
    {
        $packsData = $this->getDataFromFile($fileinfo);
        if ($this->dryRun) {
            return $this->dryRunCount($packsData, 'AppBundle\\Entity\\Pack', ['name', 'position', 'size', 'dateRelease', 'status', 'creator']);
        }
        $result = [];
        foreach ($packsData as $packData) {
            $pack = $this->getEntityFromData('AppBundle\Entity\Pack', $packData, [
                'code',
                'name',
                'position',
                'size',
                'date_release',
            ], [
                'pack_type_code',
            ], [
                'cgdb_id',
                'creator',
                'status',
                'theme',
                'visibility',
                'language',
                'environment',
            ]);
            if ($pack) {
                $result[] = $pack;
                $this->em->persist($pack);
            }
        }
        return $result;
    }

    protected function importCardsFromJsonData($cardsData)
    {
        $result = [];
        foreach ($cardsData as $cardData) {
            if (array_key_exists('alt-art', $cardData) && !array_key_exists('alt_art', $cardData)) {
                $cardData['alt_art'] = $cardData['alt-art'];
            }
            if (array_key_exists('altArt', $cardData) && !array_key_exists('alt_art', $cardData)) {
                $cardData['alt_art'] = $cardData['altArt'];
            }
            if (!array_key_exists('alt_art', $cardData)) {
                $cardData['alt_art'] = false;
            }

            if ((empty($cardData['subtype_code']) || $cardData['subtype_code'] === null)
                && !empty($cardData['set_code'])
                && array_key_exists('Cardset', $this->collections)
                && array_key_exists($cardData['set_code'], $this->collections['Cardset'])
            ) {
                $cardset = $this->collections['Cardset'][$cardData['set_code']];
                if ($cardset && $cardset->getCardSetType() && $cardset->getCardSetType()->getCode() === 'nemesis') {
                    $cardData['subtype_code'] = 'nemesis';
                }
            }

            $card = $this->getEntityFromData('AppBundle\Entity\Card', $cardData, [
                'code',
                'position',
                'quantity',
                'name',
            ], [
                'faction_code',
                'faction2_code',
                'pack_code',
                'type_code',
                'subtype_code',
                'set_code',
                'back_card_code',
                'front_card_code',
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
                'alt_art',
                'errata',
                'octgn_id',
                'creator',
            ]);
            if ($card) {
                if ($card->getName())   $card->setRealName($card->getName());
                if ($card->getTraits()) $card->setRealTraits($card->getTraits());
                if ($card->getText())   $card->setRealText($card->getText());
                $result[] = $card;
                $this->em->persist($card);
                if (isset($cardData['back_link'])) {
                    $this->links[] = ['card_id' => $card->getCode(), 'target_id' => $cardData['back_link']];
                }
            }
        }
        return $result;
    }

    protected function importCardsJsonFile(\SplFileInfo $fileinfo, $special = '')
    {
        $result = [];
        $code   = $fileinfo->getBasename('.json');
        if (stristr($code, '_encounter') !== false && $special) {
            return $result;
        }
        $code = str_replace('_encounter', '', $code);
        $pack = $this->em->getRepository('AppBundle:Pack')->findOneBy(['code' => $code]);
        if (!$pack) throw new \Exception("Unable to find Pack [{$code}]");
        $cardsData = $this->getDataFromFile($fileinfo);
        return $this->importCardsFromJsonData($cardsData);
    }

    // -------------------------------------------------------------------------
    // Card-type specific importers (delegated from importCardsFromJsonData)
    // -------------------------------------------------------------------------

    protected function importSupportData(Card $card, $data) {}

    protected function importUpgradeData(Card $card, $data)
    {
        foreach (['scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importObligationData(Card $card, $data)
    {
        foreach (['boost', 'boost_star', 'scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importHeroData(Card $card, $data)
    {
        foreach (['attack', 'defense', 'hand_size', 'health', 'thwart'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
        foreach (['attack_star', 'defense_star', 'health_star', 'scheme_acceleration', 'thwart_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importAlterEgoData(Card $card, $data)
    {
        foreach (['health', 'hand_size', 'recover'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
        foreach (['health_star', 'recover_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importAllyData(Card $card, $data)
    {
        foreach (['attack', 'health', 'thwart'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, true);
        }
        foreach (['attack_cost', 'attack_star', 'health_star', 'scheme_acceleration', 'scheme_amplify', 'scheme_hazard', 'thwart_cost', 'thwart_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importMinionData(Card $card, $data)
    {
        foreach (['attack', 'attack_star', 'boost', 'boost_star', 'health', 'health_per_group', 'health_per_hero', 'health_star', 'scheme', 'scheme_acceleration', 'scheme_amplify', 'scheme_hazard', 'scheme_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importPlayerMinionData(Card $card, $data)
    {
        $this->importMinionData($card, $data);
    }

    protected function importEnvironmentData(Card $card, $data)
    {
        foreach (['boost', 'boost_star', 'scheme_acceleration', 'scheme_amplify', 'scheme_hazard'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importEvidenceMeansData(Card $card, $data) {}
    protected function importEvidenceMotiveData(Card $card, $data) {}
    protected function importEvidenceOpportunityData(Card $card, $data) {}

    protected function importChallengeData(Card $card, $data)
    {
        $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, 'expansions_needed', false);
    }

    protected function importModularSetsData(Card $card, $data) {}

    protected function importSideSchemeData(Card $card, $data)
    {
        foreach (['base_threat', 'base_threat_fixed', 'base_threat_per_group', 'boost', 'boost_star', 'escalation_threat', 'escalation_threat_fixed', 'escalation_threat_star', 'scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importMainSchemeData(Card $card, $data)
    {
        foreach (['base_threat', 'base_threat_fixed', 'base_threat_per_group', 'escalation_threat', 'escalation_threat_fixed', 'escalation_threat_star', 'scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard', 'stage', 'threat', 'threat_fixed', 'threat_per_group', 'threat_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importPlayerSideSchemeData(Card $card, $data)
    {
        $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, 'base_threat', true);
        foreach (['base_threat_fixed', 'base_threat_per_group', 'scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importEventData(Card $card, $data)
    {
        $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, 'cost', true);
    }

    protected function importResourceData(Card $card, $data) {}

    protected function importVillainData(Card $card, $data)
    {
        $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, 'health', true);
        foreach (['attack', 'attack_star', 'health_per_group', 'health_per_hero', 'health_star', 'scheme', 'scheme_star', 'stage'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importLeaderData(Card $card, $data)
    {
        $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, 'health', true);
        foreach (['attack', 'attack_star', 'health_per_hero', 'health_star', 'scheme', 'scheme_star', 'stage'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importTreacheryData(Card $card, $data)
    {
        foreach (['boost', 'boost_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    protected function importAttachmentData(Card $card, $data)
    {
        foreach (['attack', 'attack_star', 'boost', 'boost_star', 'scheme', 'scheme_acceleration', 'scheme_amplify', 'scheme_crisis', 'scheme_hazard', 'scheme_star'] as $key) {
            $this->copyKeyToEntity($card, 'AppBundle\Entity\Card', $data, $key, false);
        }
    }

    // -------------------------------------------------------------------------
    // Entity helpers
    // -------------------------------------------------------------------------

    protected function copyFieldValueToEntity($entity, $entityName, $fieldName, $newJsonValue)
    {
        $metadata       = $this->em->getClassMetadata($entityName);
        $type           = $metadata->fieldMappings[$fieldName]['type'];
        $newTypedValue  = $newJsonValue;
        $getter         = 'get' . ucfirst($fieldName);
        $currentJsonValue = $currentTypedValue = $entity->$getter();

        if (in_array($type, ['date', 'datetime'])) {
            if ($newJsonValue !== null) {
                $newTypedValue = new \DateTime($newJsonValue);
            }
            if ($currentTypedValue !== null) {
                $currentJsonValue = ($type === 'date')
                    ? $currentTypedValue->format('Y-m-d')
                    : $currentTypedValue->format('Y-m-d H:i:s');
            }
        }

        if ($currentJsonValue !== $newJsonValue) {
            if (is_array($currentJsonValue) || is_array($newJsonValue)) {
                $this->output->writeln("  Changing <info>{$fieldName}</info> of <info>" . $entity->toString() . "</info>");
            } else {
                $this->output->writeln("  Changing <info>{$fieldName}</info> of <info>" . $entity->toString() . "</info> ({$currentJsonValue} => {$newJsonValue})");
            }
            $setter = 'set' . ucfirst($fieldName);
            $entity->$setter($newTypedValue);
        }
    }

    protected function copyKeyToEntity($entity, $entityName, $data, $key, $isMandatory = true)
    {
        $metadata = $this->em->getClassMetadata($entityName);
        if (!array_key_exists($key, $data)) {
            if ($isMandatory) {
                throw new \Exception("Missing key [{$key}] in " . json_encode($data));
            }
            if ($key === 'environment') return;
            $data[$key] = null;
        }

        $value = $data[$key];
        if (in_array($key, ['is_unique', 'hidden', 'permanent']) && !$value) {
            $value = false;
        }
        if ($key === 'deck_requirements' && $value) {
            $value = json_encode($value);
        }
        if ($key === 'environment' && ($value === null || $value === '')) {
            return;
        }
        if ($key === 'meta' && $value) {
            $value = json_encode($value);
        }
        if ($key === 'deck_options' && $value) {
            $value = json_encode($value);
        }

        if (!array_key_exists($key, $metadata->fieldNames)) {
            throw new \Exception("Missing column [{$key}] in entity {$entityName}");
        }
        $fieldName = $metadata->fieldNames[$key];
        $this->copyFieldValueToEntity($entity, $entityName, $fieldName, $value);
    }

    protected function getEntityFromData($entityName, $data, $mandatoryKeys, $foreignKeys, $optionalKeys)
    {
        if (!array_key_exists('code', $data)) {
            throw new \Exception("Missing key [code] in " . json_encode($data));
        }
        if (array_key_exists('duplicate_of', $data) && !array_key_exists('name', $data)) {
            $this->duplicates[] = ['card' => $data, 'duplicate_of' => $data['duplicate_of']];
            return;
        }

        $entity = $this->em->getRepository($entityName)->findOneBy(['code' => $data['code']]);
        if (!$entity) {
            $entity = new $entityName();
        }

        $orig = $entity->serialize();

        foreach ($mandatoryKeys as $key) {
            $this->copyKeyToEntity($entity, $entityName, $data, $key, true);
        }
        foreach ($optionalKeys as $key) {
            $this->copyKeyToEntity($entity, $entityName, $data, $key, false);
        }
        foreach ($foreignKeys as $key) {
            $foreignEntityShortName = ucfirst(str_replace('_code', '', $key));
            if ($key === 'front_card_code')    $foreignEntityShortName = 'Card';
            if ($key === 'set_code')           $foreignEntityShortName = 'Cardset';
            if ($key === 'pack_type_code')     $foreignEntityShortName = 'Packtype';
            if ($key === 'card_set_type_code') $foreignEntityShortName = 'Cardsettype';

            if (!array_key_exists($key, $data)) {
                if (in_array($key, ['faction2_code', 'subtype_code', 'set_code', 'back_card_code', 'front_card_code'])) continue;
                throw new \Exception("Missing key [{$key}] in " . json_encode($data));
            }

            $foreignCode = $data[$key];
            if (!array_key_exists($foreignEntityShortName, $this->collections)) {
                throw new \Exception("No collection for [{$foreignEntityShortName}] in " . json_encode($data));
            }
            if (!$foreignCode) continue;
            if (!array_key_exists($foreignCode, $this->collections[$foreignEntityShortName])) {
                throw new \Exception("Invalid code [{$foreignCode}] for key [{$key}] in " . json_encode($data));
            }

            $foreignEntity = $this->collections[$foreignEntityShortName][$foreignCode];
            $getter        = 'get' . $foreignEntityShortName;
            if (!$entity->$getter() || $entity->$getter()->getId() !== $foreignEntity->getId()) {
                $this->output->writeln("  Changing <info>{$key}</info> of <info>" . $entity->toString() . "</info>");
                $setter = 'set' . $foreignEntityShortName;
                $entity->$setter($foreignEntity);
            }
        }

        // Card-type specific import
        if ($entityName === 'AppBundle\Entity\Card') {
            $cleanName = $entity->getType()->getName();
            $aliases   = [
                'Alter-Ego'          => 'AlterEgo',
                'Player Minion'      => 'PlayerMinion',
                'Modular Sets'       => 'ModularSets',
                'Side Scheme'        => 'SideScheme',
                'Main Scheme'        => 'MainScheme',
                'Player Side Scheme' => 'PlayerSideScheme',
                'Evidence - Means'   => 'EvidenceMeans',
                'Evidence - Motive'  => 'EvidenceMotive',
                'Evidence - Opportunity' => 'EvidenceOpportunity',
            ];
            if (isset($aliases[$cleanName])) $cleanName = $aliases[$cleanName];
            $parts     = preg_split('/[^A-Za-z0-9]+/', $cleanName);
            $cleanName = implode('', array_map('ucfirst', array_filter($parts)));
            $functionName = 'import' . $cleanName . 'Data';
            if (method_exists($this, $functionName)) {
                $this->$functionName($entity, $data);
            } else {
                $this->output->writeln("<comment>No importer for type '" . $entity->getType()->getName() . "' (tried {$functionName}). Skipping.</comment>");
            }
        }

        if ($entity->serialize() !== $orig || (isset($data['back_link']) && (!$entity->getLinkedTo() || $entity->getLinkedTo()->getCode() != $data['back_link']))) {
            return $entity;
        }
    }

    // -------------------------------------------------------------------------
    // File helpers
    // -------------------------------------------------------------------------

    protected function getDataFromFile(\SplFileInfo $fileinfo)
    {
        $file = $fileinfo->openFile('r');
        $file->setFlags(\SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $lines = [];
        foreach ($file as $line) {
            if ($line !== false) $lines[] = $line;
        }
        $content = implode('', $lines);
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $data = json_decode($content, true);
        if ($data === null) {
            throw new \Exception("File [" . $fileinfo->getPathname() . "] contains incorrect JSON (error code " . json_last_error() . ")");
        }
        return $data;
    }

    protected function getFileInfo($path, $filename)
    {
        $fs = new Filesystem();
        if (!$fs->exists($path)) {
            throw new \Exception("No repository found at [{$path}]");
        }
        $filepath = "{$path}/{$filename}";
        if (!$fs->exists($filepath)) {
            throw new \Exception("No {$filename} file found at [{$path}]");
        }
        return new \SplFileInfo($filepath);
    }

    protected function getFileSystemIterator($path)
    {
        $fs = new Filesystem();
        if (!$fs->exists($path)) {
            throw new \Exception("No repository found at [{$path}]");
        }
        $iterator = new \GlobIterator("{$path}*.json");
        if (!$iterator->count()) {
            throw new \Exception("No json file found at [{$path}]");
        }
        return $iterator;
    }

    protected function loadCollection($entityShortName)
    {
        $this->collections[$entityShortName] = [];
        $entities = $this->em->getRepository('AppBundle:' . $entityShortName)->findAll();
        foreach ($entities as $entity) {
            $this->collections[$entityShortName][$entity->getCode()] = $entity;
        }
    }
}
