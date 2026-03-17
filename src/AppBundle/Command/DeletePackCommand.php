<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class DeletePackCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:pack:delete')
            ->setDescription('Delete decks containing beta cards')
             ->addArgument(
            'cgdb_id',
            InputArgument::REQUIRED,
            'cgdb_id of the pack'
        )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $cgdb_id = $input->getArgument('cgdb_id');
        $pack = $em->getRepository('AppBundle:Pack')->findOneBy(["cgdbId" => $cgdb_id]);
        if(!$pack) {
            $output->writeln("Couldnt find a matching pack");
            die();
        }
        $pack_id = $pack->getId();
        $dbh =  $this->getContainer()->get('doctrine')->getConnection();
        $decks = $dbh->executeQuery("SELECT DISTINCT d.deck_id FROM `deckslot` d join `card` c  on d.card_id = c.id WHERE c.pack_id = ".$pack_id.";")->fetchAll();
        foreach($decks as $index => $deck) {
            $output->writeln("Removing deck ".$deck['deck_id']);
            $em->remove($em->getReference('AppBundle:Deck', $deck['deck_id']));
        }
        $output->writeln("Deleting pack ".$pack->getName());
        // with the pack id
        $cards = $em->getRepository('AppBundle:Card')->findBy([
                'pack' => $pack_id
            ]);
        foreach($cards as $index => $card) {
            $output->writeln("Deleting ".$card->getName());
            $dupes = $card->getDuplicates();
            $card->setDuplicateOf(null);
            foreach($dupes as $i => $todelete) {
                $todelete->setDuplicateOf(null);
                $em->flush();
            }
            // detach linked cards (cards that link to this one)
            $linked = $card->getLinkedFrom();
            foreach($linked as $i => $todelete) {
                $todelete->setLinkedTo(null);
                $em->flush();
            }
            $card->setLinkedTo(null);
            $em->flush();
        }
        // Remove references and then remove cards
        foreach($cards as $index => $card) {
            $cid = $card->getId();

            // Remove decks that use this card as their character
            $characterDecks = $em->getRepository('AppBundle:Deck')->findBy(['character' => $cid]);
            foreach($characterDecks as $d) {
                $output->writeln("Removing character Deck " . $d->getId());
                $em->remove($d);
            }

            // Remove decklists that use this card as their character
            $characterDecklists = $em->getRepository('AppBundle:Decklist')->findBy(['character' => $cid]);
            foreach($characterDecklists as $dl) {
                $output->writeln("Removing Decklist " . $dl->getId());
                $em->remove($dl);
            }

            // Delete raw slot rows that reference this card (faster and avoids loading large collections)
            try {
                $dbh->executeUpdate('DELETE FROM deckslot WHERE card_id = ?', [$cid]);
                $dbh->executeUpdate('DELETE FROM sidedeckslot WHERE card_id = ?', [$cid]);
                $dbh->executeUpdate('DELETE FROM decklistslot WHERE card_id = ?', [$cid]);
                $dbh->executeUpdate('DELETE FROM sidedecklistslot WHERE card_id = ?', [$cid]);

                // Delete reviewcomments for reviews attached to this card, then delete the reviews
                $dbh->executeUpdate('DELETE rc FROM reviewcomment rc JOIN review r ON rc.review_id = r.id WHERE r.card_id = ?', [$cid]);
                $dbh->executeUpdate('DELETE FROM review WHERE card_id = ?', [$cid]);
            } catch (\Exception $e) {
                // If direct SQL delete fails, continue and let ORM handle remaining references
                $output->writeln("Warning: raw deletion of related rows failed for card {$cid}: " . $e->getMessage());
            }

            // Finally remove the card entity using a managed reference (handles detached objects)
            $managedCard = $em->getReference('AppBundle:Card', $cid);
            $em->remove($managedCard);
            // flush in moderate batches to avoid memory spikes
            if (($index % 20) === 0) {
                $em->flush();
                $em->clear();
            }
        }
        // final flush after loop
        $em->flush();
        // Remove pack using a managed reference in case it's detached
        $em->remove($em->getReference('AppBundle:Pack', $pack->getId()));
        $em->flush();

        $output->writeln("Deleted all associated decks");
    }
}