<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ListAllDecksCommand extends ContainerAwareCommand
    /**
     * Récupère les decks (privés ou publics) et leurs cartes, puis écrit le fichier TSV.
     * @param \Doctrine\DBAL\Connection $dbh Connexion DBAL
     * @param string $sqlDecks La requête SQL pour les decks
     * @param string $sqlCards La requête SQL pour les cartes d'un deck
     * @param string $filename Nom du fichier de sortie
     * @param OutputInterface $output
     * @param string $deckIdKey Clé de l'id du deck (deck_id ou decklist_id)
     * @param bool $isPublicDeck Indique si c'est un deck public (pour le message)
     */

{
    protected function configure()
    {
        $this
            ->setName('app:deck:listall')
            ->setDescription('Liste tous les decks (y compris privés) en base dans un fichier txt (deck_id, nom, user, héros)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Récupération des services Doctrine
        $em = $this->getContainer()->get('doctrine')->getManager();
        $dbh = $this->getContainer()->get('doctrine')->getConnection();

        // Export decks privés
        $sqlPrivate = "SELECT d.id as deck_id, d.name as deck_name, u.username as user_name, c.name as hero_name, c.code as hero_code
            FROM deck d
            JOIN user u ON d.user_id = u.id
            LEFT JOIN card c ON d.character_id = c.id";
        $sqlCardsPrivate = "SELECT c.name as card_name, c.code as card_code, p.name as pack_name, p.code as pack_code, f.name as faction_name, f.code as faction_code, t.name as type_name, c.permanent as permanent, ds.quantity as qty FROM deckslot ds
            JOIN card c ON ds.card_id = c.id
            JOIN pack p ON c.pack_id = p.id
            LEFT JOIN faction f ON c.faction_id = f.id
            LEFT JOIN type t ON c.type_id = t.id
            WHERE ds.deck_id = ?";
    $this->exportDecksToFile($dbh, $sqlPrivate, $sqlCardsPrivate, 'web/private_decks_list.tsv', $output, 'deck_id', false);

        // Export decks publics
        $sqlPublic = "SELECT d.id as deck_id, d.name as deck_name, u.username as user_name, c.name as hero_name, c.code as hero_code
            FROM decklist d
            JOIN user u ON d.user_id = u.id
            LEFT JOIN card c ON d.card_id = c.id";
        $sqlCardsPublic = "SELECT c.name as card_name, c.code as card_code, p.name as pack_name, p.code as pack_code, f.name as faction_name, f.code as faction_code, t.name as type_name, c.permanent as permanent, ds.quantity as qty FROM decklistslot ds
            JOIN card c ON ds.card_id = c.id
            JOIN pack p ON c.pack_id = p.id
            LEFT JOIN faction f ON c.faction_id = f.id
            LEFT JOIN type t ON c.type_id = t.id
            WHERE ds.decklist_id = ?";
    $this->exportDecksToFile($dbh, $sqlPublic, $sqlCardsPublic, 'web/public_decks_list.tsv', $output, 'deck_id', true);
        return 0;
    }

    /**
     * Récupère les decks (privés ou publics) et leurs cartes, puis écrit le fichier TSV.
     * @param \Doctrine\DBAL\Connection $dbh Connexion DBAL
     * @param string $sqlDecks La requête SQL pour les decks
     * @param string $sqlCards La requête SQL pour les cartes d'un deck
     * @param string $filename Nom du fichier de sortie
     * @param OutputInterface $output
     * @param string $deckIdKey Clé de l'id du deck (deck_id ou decklist_id)
     * @param bool $isPublicDeck Indique si c'est un deck public (pour le message)
     */
    private function exportDecksToFile($dbh, $sqlDecks, $sqlCards, $filename, OutputInterface $output, $deckIdKey, $isPublicDeck = false)
    {
        // Récupère tous les decks
        $decks = $dbh->executeQuery($sqlDecks)->fetchAll();
        $file = fopen($filename, 'w');
        if (!$file) {
            $output->writeln("Impossible d'ouvrir le fichier de sortie " . ($isPublicDeck ? 'public' : 'privé'));
            return 1;
        }
        // Détermine le nombre max de cartes dans un deck pour l'en-tête
        $maxCards = 0;
        $allCards = [];
        foreach ($decks as $deck) {
            $cards = $dbh->executeQuery($sqlCards, [$deck[$deckIdKey]])->fetchAll();
            $allCards[$deck[$deckIdKey]] = $cards;
            if (count($cards) > $maxCards) {
                $maxCards = count($cards);
            }
        }
        // Génère l'en-tête dynamique avec la colonne creator après user_name
        $header = ["deck_id", "deck_name", "user_name", "creator", "card_count", "hero_name"];
        for ($i = 1; $i <= $maxCards; $i++) {
            $header[] = "card_$i";
        }
        fwrite($file, implode("\t", $header) . "\n");

        // Prépare une requête pour récupérer le creator du pack à partir du code du pack
        $sqlPackCreator = "SELECT creator FROM pack WHERE code = ?";

        // Écrit chaque ligne deck + cartes
        foreach ($decks as $deck) {
            $hero = $deck['hero_name'] ?? '';
            $hero_code = $deck['hero_code'] ?? '';
            $pack_creator = '';
            if ($hero && $hero_code) {
                $hero .= ' [' . $hero_code . ']';
                // On récupère le code du pack du héros
                $sqlHeroPack = "SELECT p.code FROM card c JOIN pack p ON c.pack_id = p.id WHERE c.code = ?";
                $heroPackRow = $dbh->executeQuery($sqlHeroPack, [$hero_code])->fetch();
                if ($heroPackRow && isset($heroPackRow['code'])) {
                    $pack_code = $heroPackRow['code'];
                    $packCreatorRow = $dbh->executeQuery($sqlPackCreator, [$pack_code])->fetch();
                    if ($packCreatorRow && isset($packCreatorRow['creator']) && $packCreatorRow['creator']) {
                        $pack_creator = $packCreatorRow['creator'];
                    }
                }
            }
            if (!$pack_creator) {
                $pack_creator = 'FFG';
            }
            $cards = $allCards[$deck[$deckIdKey]];
            // Calcul du nombre de cartes hors héros et cartes permanentes (permanent = colonne booléenne)
            $card_count = 0;
            foreach ($cards as $card) {
                $type_name = strtolower($card['type_name'] ?? '');
                $is_permanent = isset($card['permanent']) ? (bool)$card['permanent'] : false;
                if ($type_name !== 'hero' && !$is_permanent) {
                    $card_count += (int)$card['qty'];
                }
            }
            $row = [$deck['deck_id'], $deck['deck_name'], $deck['user_name'], $pack_creator, $card_count, $hero];
            foreach ($cards as $card) {
                $qty = $card['qty'];
                $name = $card['card_name'];
                $card_code = $card['card_code'];
                $pack_code = $card['pack_code'];
                $pack_name = $card['pack_name'];
                $faction_name = $card['faction_name'] ?? '';
                $type_name = $card['type_name'] ?? '';
                $ofhero = $faction_name ? ' --of' . str_replace(' ', '', $faction_name) : '';
                $row[] = $qty . 'x ' . $name . ' [' . $card_code . '] (' . $pack_name . ')' . $ofhero . ' --pc' . $pack_code . ' --ct' . $type_name;
            }
            // Remplit les colonnes manquantes
            while (count($row) < 6 + $maxCards) {
                $row[] = '';
            }
            fwrite($file, implode("\t", $row) . "\n");
        }
        fclose($file);
        $output->writeln("Fichier $filename généré avec succès (" . count($decks) . " decks " . ($isPublicDeck ? 'publics' : 'privés') . ").");
        return 0;
    }
}
