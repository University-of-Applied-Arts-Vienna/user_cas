<?php


namespace OCA\UserCAS\Command;

use OC\User\Manager;
use OCA\UserCAS\Service\Import\AdImporter;
use OCA\UserCAS\Service\Import\ImporterInterface;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class ImportUsersAd
 * @package OCA\UserCAS\Command
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class ImportUsersAd extends Command
{

    /**
     * @var Manager $userManager
     */
    private $userManager;

    /**
     * @var IConfig
     */
    private $config;


    /**
     * ImportUsersAd constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->userManager = \OC::$server->getUserManager();
        $this->config = \OC::$server->getConfig();
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {


        try {
            /**
             * @var LoggerInterface $logger
             */
            $logger = new ConsoleLogger($output);

            # Check for ldap extension
            if (extension_loaded("ldap")) {

                /**
                 * @var ImporterInterface $importer
                 */
                $importer = new AdImporter($this->config);

                $importer->init($logger);

                $allUsers = $importer->getUsers();

                $importer->close();


                $createCommand = $this->getApplication()->find('cas:create-user');
                $updateCommand = $this->getApplication()->find('cas:update-user');

                $returnCode = FALSE;

                foreach ($allUsers as $user) {

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
                    if ($this->userManager->userExists($user["uid"])) {

                        $arguments["--convert-backend"] = 1;
                        $input = new ArrayInput($arguments);

                        $updateCommand->run($input, new NullOutput());
                    } else { # Create user if he does not exist

                        $input = new ArrayInput($arguments);

                        $createCommand->run($input, new NullOutput());
                    }
                }

                $logger->notice("AD import finished with code: " . $returnCode);
            } else {

                $logger->warning("AD import failed. PHP extension 'ldap' is not loaded.");
            }
        }
        catch(\Exception $e) {

            $logger->critical("Fatal Error: ".$e->getMessage());
        }
    }
}