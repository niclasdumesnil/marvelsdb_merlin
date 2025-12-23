<?php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCampaignCheckboxCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'app:import:campaign-checkbox';

    protected function configure()
    {
        $this
            ->setDescription('Import campaign_checkbox arrays from marvelsdb_fanmade_data/campaigns.json into Campaign records')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getContainer()->get('kernel');
        $root = $kernel->getProjectDir();
        $path = $root . DIRECTORY_SEPARATOR . 'marvelsdb_fanmade_data' . DIRECTORY_SEPARATOR . 'campaigns.json';
        if (!file_exists($path)) {
            $output->writeln('<error>campaigns.json not found at ' . $path . '</error>');
            return 1;
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $output->writeln('<error>Invalid JSON</error>');
            return 1;
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Campaign');

        $count = 0;
        foreach ($data as $entry) {
            if (!isset($entry['code'])) continue;
            $code = $entry['code'];
            $camp = $repo->findOneBy(['code' => $code]);
            if (!$camp) {
                $output->writeln('<comment>Campaign not found in DB: ' . $code . '</comment>');
                continue;
            }
            if (isset($entry['campaign_checkbox'])) {
                $camp->setCampaignCheckbox(json_encode($entry['campaign_checkbox']));
                $em->persist($camp);
                $count++;
                $output->writeln('Updated ' . $code);
            }
        }
        $em->flush();
        $output->writeln('Imported campaign_checkbox for ' . $count . ' campaigns.');
        return 0;
    }
}
