<?php


namespace OCA\UserCAS\Command;

use OCA\UserCAS\Importer\AdImporter;
use OCA\UserCAS\Importer\ImporterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class AdImportUser
 * @package FelixRupp\UserCasImport\Importer
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class ImportUsersAd extends Command
{

    /**
     * @var \OC\User\Manager $userManager
     */
    private $userManager;

    public function __construct()
    {
        parent::__construct();

        $this->userManager = \OC::$server->getUserManager();
    }

    /**
     * Configure method
     */
    protected function configure()
    {
        $this
            ->setName('cas:import-users-ad')
            ->setDescription('Imports users from an ActiveDirectory LDAP.');
    }

    /**
     * Execute method
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        /**
         * @var LoggerInterface $logger
         */
        $logger = new ConsoleLogger($output);

        /**
         * @var ImporterInterface $importer
         */
        $importer = new AdImporter();

        $importer->init($logger);

        $allUsers = $importer->getUsers();

        $importer->close();


        $createCommand = $this->getApplication()->find('cas:create-user');
        $updateCommand = $this->getApplication()->find('cas:update-user');

        $returnCode = FALSE;

        foreach ($allUsers as $employeeId => $user) {

            $arguments = [
                'command' => 'cas:create-user',
                'uid' => $user["uid"],
                '--display-name' => $user["displayName"],
                '--email' => $user["email"],
                '--quota' => $user["quota"],
                '--enabled' => $user["enable"],
                '--group' => $user["groups"]
            ];

            # Update user if he already exists
            if ($this->userManager->userExists($employeeId)) {

                $arguments["--convert-backend"] = 1;
                $input = new ArrayInput($arguments);

                $returnCode = $updateCommand->run($input, $output);
            } else { # Create user if he does not exist

                $input = new ArrayInput($arguments);

                $returnCode = $createCommand->run($input, $output);
            }
        }

        $logger->notice("AD import finished with code: " . $returnCode);
    }
}