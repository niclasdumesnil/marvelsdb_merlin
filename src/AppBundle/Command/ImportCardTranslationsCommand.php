<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\DBAL\Connection;

/**
 * Imports card translations from JSON pack files into the card_translation table.
 *
 * Usage:
 *   php bin/console app:import:card-translations ../../marvelsdb_fanmade_data
 *   php bin/console app:import:card-translations ../../marvelsdb_fanmade_data --locale=fr
 *
 * The path argument is relative to the project root directory.
 * All *.json files found recursively under {path}/translations/{locale}/pack/ are parsed.
 *
 * The table card_translation must exist (run migrations/card_translation.sql first).
 */
class ImportCardTranslationsCommand extends ContainerAwareCommand
{
    /** @var Connection */
    private $conn;

    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:import:card-translations')
            ->setDescription('Import card translations from JSON pack files into card_translation table')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to the fanmade data repository (relative to project root, e.g. ../../marvelsdb_fanmade_data)'
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

        // Resolve the repository root relative to the current working directory
        // (where the user runs `php bin/console` from), with absolute path as fallback.
        $rawPath  = $input->getArgument('path');
        $repoRoot = realpath(getcwd() . DIRECTORY_SEPARATOR . $rawPath);

        if ($repoRoot === false || !is_dir($repoRoot)) {
            // Try interpreting path as absolute
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

        foreach ($locales as $locale) {
            $packDir = $repoRoot . DIRECTORY_SEPARATOR . 'translations'
                     . DIRECTORY_SEPARATOR . $locale
                     . DIRECTORY_SEPARATOR . 'pack';

            if (!is_dir($packDir)) {
                $output->writeln("<error>Pack directory not found: [{$packDir}]</error>");
                continue;
            }

            $output->writeln("Importing <info>{$locale}</info> translations from <info>{$packDir}</info>");

            $files = $this->collectJsonFiles($packDir);

            if (count($files) === 0) {
                $output->writeln("<comment>No JSON files found in [{$packDir}]</comment>");
                continue;
            }

            $output->writeln(sprintf("  Found <info>%d</info> file(s).", count($files)));

            $progress = new ProgressBar($output, count($files));
            $progress->start();

            foreach ($files as $file) {
                $cards = $this->parseJsonFile($file);

                foreach ($cards as $card) {
                    if (empty($card['code'])) {
                        continue;
                    }

                    if (!$isDryRun) {
                        $result = $this->upsert($locale, $card);
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

        if (!$isDryRun) {
            $output->writeln(sprintf(
                "Done. <info>%d</info> inserted, <info>%d</info> updated.",
                $totalInserted,
                $totalUpdated
            ));
        }

        return 0;
    }

    /**
     * Recursively collects all *.json files under $dir.
     *
     * @return \SplFileInfo[]
     */
    private function collectJsonFiles(string $dir): array
    {
        $rii   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];

        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }
            $files[] = $file;
        }

        return $files;
    }

    /**
     * Parses a JSON file and returns an array of card data arrays.
     *
     * @throws \RuntimeException
     */
    private function parseJsonFile(\SplFileInfo $file): array
    {
        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            throw new \RuntimeException("Cannot read file [{$file->getPathname()}]");
        }

        $data = json_decode($content, true);

        if ($data === null) {
            throw new \RuntimeException(
                "Invalid JSON in [{$file->getPathname()}]: " . json_last_error_msg()
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException("Expected a JSON array in [{$file->getPathname()}]");
        }

        return $data;
    }

    /**
     * Inserts or updates a row in card_translation.
     * Returns 'insert' or 'update'.
     */
    private function upsert(string $locale, array $card): string
    {
        $code    = $card['code'];
        $name    = isset($card['name'])    ? (string) $card['name']    : null;
        $subname = isset($card['subname']) ? (string) $card['subname'] : null;
        $text    = isset($card['text'])    ? (string) $card['text']    : null;
        $flavor  = isset($card['flavor'])  ? (string) $card['flavor']  : null;
        $traits  = isset($card['traits'])  ? (string) $card['traits']  : null;
        $errata  = isset($card['errata'])  ? (string) $card['errata']  : null;

        // Check whether a row already exists for this (locale, code) pair
        $existing = $this->conn->fetchColumn(
            'SELECT id FROM card_translation WHERE locale = ? AND code = ?',
            [$locale, $code]
        );

        if ($existing) {
            $this->conn->executeUpdate(
                'UPDATE card_translation SET name = ?, subname = ?, text = ?, flavor = ?, traits = ?, errata = ? WHERE locale = ? AND code = ?',
                [$name, $subname, $text, $flavor, $traits, $errata, $locale, $code]
            );
            return 'update';
        }

        $this->conn->executeUpdate(
            'INSERT INTO card_translation (locale, code, name, subname, text, flavor, traits, errata) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$locale, $code, $name, $subname, $text, $flavor, $traits, $errata]
        );
        return 'insert';
    }
}
