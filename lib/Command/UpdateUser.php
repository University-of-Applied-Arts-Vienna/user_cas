<?php

namespace OCA\UserCAS\Command;

use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Service\UserService;

use OCA\UserCAS\User\Backend;
use OCA\UserCAS\User\NextBackend;
use OCA\UserCAS\User\UserCasBackendInterface;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;


/**
 * Class UpdateUser
 *
 * @package OCA\UserCAS\Command
 *
 * @author Original contributors
 * @copyright Original contributors
 *
 * @since 1.7.0
 */
class UpdateUser extends Command
{

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var AppService
     */
    protected $appService;

    /**
     * @var IUserManager
     */
    protected $userManager;

    /**
     * @var IGroupManager
     */
    protected $groupManager;

    /**
     * @var IMailer
     */
    protected $mailer;

    /**
     * @var LoggingService
     */
    protected $loggingService;

    /**
     * @var \OCP\IConfig
     */
    protected $config;

    /**
     * @var Backend|UserCasBackendInterface
     */
    protected $backend;


    /**
     *
     */
    public function __construct()
    {
        parent::__construct();

        $userManager = \OC::$server->getUserManager();
        $groupManager = \OC::$server->getGroupManager();
        $mailer = \OC::$server->getMailer();
        $config = \OC::$server->getConfig();
        $userSession = \OC::$server->getUserSession();
        $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
        $urlGenerator = \OC::$server->getURLGenerator();
        $appManager = \OC::$server->getAppManager();

        $loggingService = new LoggingService('user_cas', $config, $logger);
        $this->appService = new AppService('user_cas', $config, $loggingService, $userManager, $userSession, $urlGenerator, $appManager);

        $userService = new UserService(
            'user_cas',
            $config,
            $userManager,
            $userSession,
            $groupManager,
            $this->appService,
            $loggingService
        );

        if ($this->appService->isNotNextcloud()) {

            $backend = new Backend(
                'user_cas',
                $config,
                $loggingService,
                $this->appService,
                $userManager,
                $userService
            );
        } else {

            $backend = new NextBackend(
                'user_cas',
                $config,
                $loggingService,
                $this->appService,
                $userManager,
                $userService
            );
        }

        $this->userService = $userService;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->mailer = $mailer;
        $this->loggingService = $loggingService;
        $this->config = $config;
        $this->backend = $backend;
    }


    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('cas:update-user')
            ->setDescription('Updates an existing user and, if not yet a CAS user, converts the record to CAS backend.')
            ->addArgument(
                'uid',
                InputArgument::REQUIRED,
                'User ID used to login (must only contain a-z, A-Z, 0-9, -, _ and @).'
            )
            ->addOption(
                'display-name',
                null,
                InputOption::VALUE_OPTIONAL,
                'User name used in the web UI (can contain any characters).'
            )
            ->addOption(
                'email',
                null,
                InputOption::VALUE_OPTIONAL,
                'Email address for the user.'
            )
            ->addOption(
                'group',
                'g',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'The groups the user should be added to (The group will be created if it does not exist).'
            )
            ->addOption(
                'quota',
                'o',
                InputOption::VALUE_OPTIONAL,
                'The quota the user should get, either as numeric value in bytes or as a human readable string (e.g. 1GB for 1 Gigabyte)'
            )
            ->addOption(
                'enabled',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Set user enabled'
            )
            ->addOption(
                'convert-backend',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Convert the backend to CAS'
            );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
protected function execute(InputInterface $input, OutputInterface $output)
{
    $uid = $input->getArgument('uid');
    if (!$this->userManager->userExists($uid)) {
        $output->writeln('<error>The user "' . $uid . '" does not exist.</error>');
        return 1;
    }

    // Validate email before we create the user
    $email = null;
    if ($input->getOption('email')) {
        if (!$this->mailer->validateMailAddress($input->getOption('email'))) {
            $output->writeln('<error>Invalid email address supplied</error>');
            return 1;
        } else {
            $email = $input->getOption('email');
        }
    }

    # Register Backend
    $this->userService->registerBackend($this->backend);

    $user = $this->userManager->get($uid);
    if (!$user instanceof IUser) {
        $output->writeln('<error>An error occurred while finding the user</error>');
        return 1;
    }

    $output->writeln('<info>The user "' . $user->getUID() . '" has been found</info>');

    # Set displayName
    if ($displayName = $input->getOption('display-name')) {
        $user->setDisplayName($displayName);
        $output->writeln('Display name set to "' . $user->getDisplayName() . '"');
    }

    # Set email if supplied & valid
    if ($email !== null) {
        $user->setEMailAddress($email);
        $output->writeln('Email address set to "' . $user->getEMailAddress() . '"');
    }

    # Set Groups
    $groupStats = [
        'groups_created' => 0,
        'memberships_added' => 0,
        'memberships_removed' => 0,
    ];
    $userDeactivated = 0;
    $enabled = $input->getOption('enabled');
    $newEnabled = null;
    $clearGroupsForDisabledUser = ($enabled !== null && filter_var($enabled, FILTER_VALIDATE_BOOLEAN) === false);

    $groups = (array)$input->getOption('group');
    if (count($groups) > 0 && !$clearGroupsForDisabledUser) {
        $groupStats = $this->userService->updateGroups($user, $groups, $this->config->getAppValue('user_cas', 'cas_protected_groups'));
        $output->writeln('Groups have been updated.');
    } elseif (count($groups) > 0 && $clearGroupsForDisabledUser) {
        $output->writeln('Provided groups were ignored because the user is disabled.');
    }

    # Set Quota
    if ($quota = $input->getOption('quota')) {
        $newQuota = is_numeric($quota) ? $quota : (\OCP\Util::computerFileSize($quota) ?: 'default');
        $user->setQuota($newQuota);
        $output->writeln('Quota set to "' . $user->getQuota() . '"');
    }

    # Set enabled
    if ($enabled !== null) {
        $wasEnabled = $user->isEnabled();
        $newEnabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
        $user->setEnabled($newEnabled);
        if ($wasEnabled && !$user->isEnabled()) {
            $userDeactivated = 1;
        }
        $output->writeln('User "' . $user->getUID() . '" is now ' . ($user->isEnabled() ? 'enabled' : 'disabled'));
    }

    if ($clearGroupsForDisabledUser) {
        $groupStats = $this->userService->updateGroups($user, [], '');
        $output->writeln('All groups have been removed because the user is disabled.');
    }

    # Convert backend
    if ($convertBackend = $input->getOption('convert-backend')) {
        if ($this->appService->isNotNextcloud()) {
            $query = \OC_DB::prepare('UPDATE `*PREFIX*accounts` SET `backend` = ? WHERE LOWER(`user_id`) = LOWER(?)');
            $result = $query->execute([get_class($this->backend), $uid]);
            if (!$result) {
                $output->writeln('<error>Error occurred while updating the backend.</error>');
                return 1;
            }
            $output->writeln('User "' . $uid . '" added to CAS backend.');
        } else {
            $output->writeln('This is a Nextcloud instance, no backend update needed.');
        }
    }

    $output->writeln(sprintf(
        'IMPORT_STATS users_added=0 users_updated=1 users_deactivated=%d groups_created=%d group_memberships_added=%d group_memberships_removed=%d',
        $userDeactivated,
        $groupStats['groups_created'],
        $groupStats['memberships_added'],
        $groupStats['memberships_removed']
    ));

    // If all operations were successful, return 0
    return 0;
}
}
