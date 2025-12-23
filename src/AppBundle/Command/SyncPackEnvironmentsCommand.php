<?php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncPackEnvironmentsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:sync:pack-environments')
            ->setDescription('Sync pack.environment from a packs.json file into the database')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to packs.json (defaults to project root ./marvelsdb_fanmade_data/packs.json)')
            ->addOption('force-dateupdate', null, InputOption::VALUE_NONE, 'If set, update pack.dateUpdate to now to trigger Last-Modified changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernelRoot = $this->getContainer()->get('kernel')->getRootDir();
        $defaultPath = realpath($kernelRoot . '/..') . DIRECTORY_SEPARATOR . 'marvelsdb_fanmade_data' . DIRECTORY_SEPARATOR . 'packs.json';
        $file = $input->getOption('file') ?: $defaultPath;

        if (!file_exists($file)) {
            $output->writeln(sprintf('<error>File not found: %s</error>', $file));
            return 1;
        }

        $content = file_get_contents($file);
        $json = json_decode($content, true);
        if (!is_array($json)) {
            $output->writeln('<error>Invalid JSON in packs file</error>');
            return 1;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Pack');
        $count = 0;
        foreach ($json as $p) {
            if (!isset($p['code'])) continue;
            $code = $p['code'];
            $env = null;
            if (isset($p['environment']) && $p['environment'] !== null && trim($p['environment']) !== '') {
                $env = strtolower(trim($p['environment']));
            }
            $pack = $repo->findOneBy(array('code' => $code));
            if ($pack) {
                $pack->setEnvironment($env);
                if ($input->getOption('force-dateupdate')) {
                    $pack->setDateUpdate(new \DateTime());
                }
                $em->persist($pack);
                $count++;
            }
        }
        $em->flush();
        $output->writeln(sprintf('<info>Updated environment for %d packs from %s</info>', $count, $file));
        if ($input->getOption('force-dateupdate')) {
            $output->writeln('<info>Updated dateUpdate for modified packs.</info>');
        }

        return 0;
    }
}
