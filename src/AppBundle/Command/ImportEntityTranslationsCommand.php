<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Doctrine\DBAL\Connection;

/**
 * Imports entity translations (faction, type, subtype, pack, packtype, cardset)
 * from JSON files into the Gedmo ext_translations table.
 *
 * Usage:
 *   php bin/console app:import:entity-translations ../../marvelsdb_fanmade_data
 *   php bin/console app:import:entity-translations ../../marvelsdb_fanmade_data --locale=fr
 *   php bin/console app:import:entity-translations ../../marvelsdb_fanmade_data --dry-run
 *
 * JSON files expected at: {path}/translations/{locale}/{entity}.json
 * Each file is a JSON array of objects with at least "code" and "name" keys.
 *
 * Cards are NOT handled here — use app:import:card-translations for cards.
 */
class ImportEntityTranslationsCommand extends ContainerAwareCommand
{
    /**
     * Map of JSON file base-name → [ DB table, Doctrine FQCN, translatable fields ].
     *
     * "fields" lists every column in ext_translations that is translatable for this entity.
     * For all simple entities the only translatable field is "name".
     */
    private static $ENTITY_MAP = [
        'factions'  => [
            'table'  => 'faction',
            'class'  => 'AppBundle\\Entity\\Faction',
            'fields' => ['name'],
        ],
        'types'     => [
            'table'  => 'type',
            'class'  => 'AppBundle\\Entity\\Type',
            'fields' => ['name'],
        ],
        'subtypes'  => [
            'table'  => 'Subtype',
            'class'  => 'AppBundle\\Entity\\Subtype',
            'fields' => ['name'],
        ],
        'packs'     => [
            'table'  => 'pack',
            'class'  => 'AppBundle\\Entity\\Pack',
            'fields' => ['name'],
        ],
        'packtypes' => [
            'table'  => 'Packtype',
            'class'  => 'AppBundle\\Entity\\Packtype',
            'fields' => ['name'],
        ],
        // JSON file is "sets.json" but the entity/table is "Cardset"
        'sets'      => [
            'table'  => 'Cardset',
            'class'  => 'AppBundle\\Entity\\Cardset',
            'fields' => ['name'],
        ],
    ];

    /** @var Connection */
    private $conn;

    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:import:entity-translations')
            ->setDescription('Import entity translations (faction/type/subtype/pack/packtype/cardset) into ext_translations')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the data repository (relative to project root, e.g. ../../marvelsdb_fanmade_data)'
            )
            ->addOption(
                'locale',
                'l',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Locale(s) to import (default: fr)',
                ['fr']
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
        $this->output = $output;
        $this->conn   = $this->getContainer()->get('doctrine.dbal.default_connection');

        // Resolve the repository root (relative to cwd, fallback to absolute)
        $rawPath  = $input->getArgument('path');
        $repoRoot = realpath(getcwd() . DIRECTORY_SEPARATOR . $rawPath);

        if ($repoRoot === false || !is_dir($repoRoot)) {
            $repoRoot = realpath($rawPath);
        }

        if ($repoRoot === false || !is_dir($repoRoot)) {
            $output->writeln("<error>Repository not found at [{$rawPath}] (cwd: " . getcwd() . ")</error>");
            return 1;
        }

        $isDryRun = $input->getOption('dry-run');
        $locales  = $input->getOption('locale');

        $output->writeln("Repository : <info>{$repoRoot}</info>");
        if ($isDryRun) {
            $output->writeln("<comment>Dry-run mode — no data will be written.</comment>");
        }

        $totalInserted = 0;
        $totalUpdated  = 0;
        $totalSkipped  = 0;

        foreach ($locales as $locale) {
            $transDir = $repoRoot
                . DIRECTORY_SEPARATOR . 'translations'
                . DIRECTORY_SEPARATOR . $locale;

            if (!is_dir($transDir)) {
                $output->writeln("<error>Translation directory not found: [{$transDir}]</error>");
                continue;
            }

            $output->writeln("Importing translations for locale <info>{$locale}</info>");

            foreach (self::$ENTITY_MAP as $jsonName => $entityDef) {
                $jsonFile = $transDir . DIRECTORY_SEPARATOR . $jsonName . '.json';

                if (!file_exists($jsonFile)) {
                    $output->writeln("  <comment>Skipped (file not found): {$jsonName}.json</comment>");
                    continue;
                }

                $items = $this->parseJsonFile($jsonFile);

                $output->writeln(sprintf(
                    "  Importing <info>%s</info> (<comment>%d</comment> items)…",
                    $jsonName,
                    count($items)
                ));

                $progress = new ProgressBar($output, count($items));
                $progress->start();

                foreach ($items as $item) {
                    if (empty($item['code'])) {
                        $totalSkipped++;
                        $progress->advance();
                        continue;
                    }

                    // Resolve the numeric entity ID from its own table
                    $entityId = $this->resolveEntityId($entityDef['table'], $item['code']);

                    if ($entityId === null) {
                        $output->writeln(
                            "\n  <comment>Unknown code [{$item['code']}] in table [{$entityDef['table']}] — skipped.</comment>"
                        );
                        $totalSkipped++;
                        $progress->advance();
                        continue;
                    }

                    if (!$isDryRun) {
                        foreach ($entityDef['fields'] as $field) {
                            if (!isset($item[$field])) {
                                continue;
                            }

                            $result = $this->upsert(
                                $locale,
                                $entityDef['class'],
                                $field,
                                (string) $entityId,
                                (string) $item[$field]
                            );

                            if ($result === 'insert') {
                                $totalInserted++;
                            } else {
                                $totalUpdated++;
                            }
                        }
                    }

                    $progress->advance();
                }

                $progress->finish();
                $output->writeln('');
            }
        }

        if (!$isDryRun) {
            $output->writeln(sprintf(
                "Done. <info>%d</info> inserted, <info>%d</info> updated, <comment>%d</comment> skipped.",
                $totalInserted,
                $totalUpdated,
                $totalSkipped
            ));
        }

        return 0;
    }

    /**
     * Returns the numeric primary-key id of the entity identified by $code,
     * or null if no matching row is found.
     */
    private function resolveEntityId(string $table, string $code): ?int
    {
        $id = $this->conn->fetchColumn(
            "SELECT id FROM `{$table}` WHERE code = ?",
            [$code]
        );

        return $id !== false ? (int) $id : null;
    }

    /**
     * Upserts a single row in ext_translations.
     * The unique key is (locale, object_class, field, foreign_key).
     * Returns 'insert' or 'update'.
     */
    private function upsert(
        string $locale,
        string $objectClass,
        string $field,
        string $foreignKey,
        string $content
    ): string {
        $existing = $this->conn->fetchColumn(
            'SELECT id FROM ext_translations
              WHERE locale = ? AND object_class = ? AND field = ? AND foreign_key = ?',
            [$locale, $objectClass, $field, $foreignKey]
        );

        if ($existing) {
            $this->conn->executeUpdate(
                'UPDATE ext_translations SET content = ?
                  WHERE locale = ? AND object_class = ? AND field = ? AND foreign_key = ?',
                [$content, $locale, $objectClass, $field, $foreignKey]
            );
            return 'update';
        }

        $this->conn->executeUpdate(
            'INSERT INTO ext_translations (locale, object_class, field, foreign_key, content)
              VALUES (?, ?, ?, ?, ?)',
            [$locale, $objectClass, $field, $foreignKey, $content]
        );
        return 'insert';
    }

    /**
     * Parses a JSON file and returns an array of entity data arrays.
     *
     * @throws \RuntimeException
     */
    private function parseJsonFile(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Cannot read file [{$path}]");
        }

        $data = json_decode($content, true);

        if ($data === null) {
            throw new \RuntimeException(
                "Invalid JSON in [{$path}]: " . json_last_error_msg()
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException("Expected a JSON array in [{$path}]");
        }

        return $data;
    }
}
