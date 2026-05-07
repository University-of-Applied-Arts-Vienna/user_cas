<?php

namespace OCA\UserCAS\Command;

use OCA\UserCAS\Service\Import\AdImporter;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Inspect one LDAP person and the mapped import payload.
 */
class DebugUserAd extends Command
{
    /**
     * @var IConfig
     */
    private $config;

    public function __construct()
    {
        parent::__construct();

        $this->config = \OC::$server->getConfig();
    }

    protected function configure()
    {
        $this
            ->setName('cas:debug-user-ad')
            ->setDescription('Shows the LDAP data and mapped import payload for one person.')
            ->addArgument(
                'identifier',
                InputArgument::REQUIRED,
                'The person identifier to look up.'
            )
            ->addOption(
                'attribute',
                'a',
                InputOption::VALUE_OPTIONAL,
                'LDAP attribute to query with. Defaults to the configured UID mapping.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!extension_loaded("ldap")) {
            $output->writeln('<error>PHP extension "ldap" is not loaded.</error>');
            return 1;
        }

        $identifier = (string)$input->getArgument('identifier');
        $attribute = (string)$input->getOption('attribute');
        $logger = new ConsoleLogger($output);
        $importer = new AdImporter($this->config);

        try {
            $importer->init($logger);
            $matches = $importer->debugUser($identifier, $attribute);

            if (count($matches) === 0) {
                $output->writeln(sprintf(
                    '<comment>No LDAP entries found for identifier="%s"%s.</comment>',
                    $identifier,
                    $attribute !== '' ? sprintf(' using attribute="%s"', $attribute) : ''
                ));
                return 0;
            }

            foreach ($matches as $index => $match) {
                $output->writeln(sprintf(
                    '<info>LDAP match %d: uid="%s", displayName="%s"</info>',
                    $index + 1,
                    (string)$match['mapped_user']['uid'],
                    (string)$match['mapped_user']['displayName']
                ));
                $output->writeln(json_encode($match, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return 0;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        } finally {
            try {
                $importer->close();
            } catch (\Throwable $e) {
            }
        }
    }
}
