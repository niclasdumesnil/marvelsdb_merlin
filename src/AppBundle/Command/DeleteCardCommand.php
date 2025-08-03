<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class DeleteCardCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('app:card:delete')
            ->setDescription('Delete a card by its id')
            ->addArgument(
                'card_id',
                InputArgument::REQUIRED,
                'ID of the card to delete'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $card_code = $input->getArgument('card_id');
        $card = $em->getRepository('AppBundle:Card')->findOneBy(['code' => $card_code]);

        if (!$card) {
            $output->writeln("Card with code $card_code not found.");
            return 1;
        }

        // Gestion des duplicates : on retire les liens de duplication
        if (method_exists($card, 'getDuplicates')) {
            foreach ($card->getDuplicates() as $dupe) {
                if (method_exists($dupe, 'setDuplicateOf')) {
                    $dupe->setDuplicateOf(null);
                }
            }
        }
        if (method_exists($card, 'setDuplicateOf')) {
            $card->setDuplicateOf(null);
        }

        $output->writeln("Deleting card: " . $card->getName());
        $em->remove($card);
        $em->flush();

        $output->writeln("Card deleted successfully.");
        return 0;
    }
}