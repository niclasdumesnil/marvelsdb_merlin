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
            $linked = $card->getLinkedFrom();
            foreach($dupes as $i => $todelete) {
                $todelete->setLinkedTo(null);
                $em->flush();
            }
            $card->setLinkedTo(null);
            $em->flush();
        }
        foreach($cards as $index => $card) {
            $em->remove($card);
            $em->flush();
        }
        $em->remove($pack);
        $em->flush();

        $output->writeln("Deleted all associated decks");
    }
}