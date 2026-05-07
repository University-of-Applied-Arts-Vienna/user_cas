<?php

/**
 * ownCloud - user_cas
 *
 * @author Original contributors
 * @copyright Original contributors
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IContainer;
use OCA\UserCAS\Service\UserService;
use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Hooks\UserHooks;
use OCA\UserCAS\Controller\SettingsController;
use OCA\UserCAS\Controller\AuthenticationController;
use OCA\UserCAS\User\Backend;
use OCA\UserCAS\User\NextBackend;
use OCA\UserCAS\Service\LoggingService;

/**
 * Class Application
 *
 * @package OCA\UserCAS\AppInfo
 *
 * @author Original contributors
 * @copyright Original contributors
 *
 * @since 1.4.0
 */
class Application extends App implements IBootstrap
{

    /**
     * Application constructor.
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = array())
    {

        parent::__construct('user_cas', $urlParams);

        $container = $this->getContainer();

        $container->registerService('User', function (IContainer $c) {
            return $c->query('UserSession')->getUser();
        });

        $container->registerService('Config', function (IContainer $c) {
            return $c->query('ServerContainer')->getConfig();
        });

        $container->registerService('L10N', function (IContainer $c) {
            return $c->query('ServerContainer')->getL10N($c->query('AppName'));
        });

        $container->registerService('Logger', function (IContainer $c) {
            return $c->query('ServerContainer')->get(\Psr\Log\LoggerInterface::class);
        });

        /**
         * Register LoggingService
         */
        $container->registerService('LoggingService', function (IContainer $c) {
            return new LoggingService(
                $c->query('AppName'),
                $c->query('Config'),
                $c->query('Logger')
            );
        });

        /**
         * Register AppService with config
         */
        $container->registerService('AppService', function (IContainer $c) {
            return new AppService(
                $c->query('AppName'),
                $c->query('Config'),
                $c->query('LoggingService'),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getUserSession(),
                $c->query('ServerContainer')->getURLGenerator(),
                $c->query('ServerContainer')->getAppManager()
            );
        });


        // Workaround for Nextcloud >= 14.0.0
        if ($container->query('AppService')->isNotNextcloud()) {

            /**
             * Register regular Backend
             */
            $container->registerService('Backend', function (IContainer $c) {
                return new Backend(
                    $c->query('AppName'),
                    $c->query('Config'),
                    $c->query('LoggingService'),
                    $c->query('AppService'),
                    $c->query('ServerContainer')->getUserManager(),
                    $c->query('UserService')
                );
            });
        } else {

            /**
             * Register Nextcloud Backend
             */
            $container->registerService('Backend', function (IContainer $c) {
                return new NextBackend(
                    $c->query('AppName'),
                    $c->query('Config'),
                    $c->query('LoggingService'),
                    $c->query('AppService'),
                    $c->query('ServerContainer')->getUserManager(),
                    $c->query('UserService')
                );
            });
        }

        /**
         * Register UserService with UserSession for login/logout and UserManager for create
         */
        $container->registerService('UserService', function (IContainer $c) {
            return new UserService(
                $c->query('AppName'),
                $c->query('Config'),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getUserSession(),
                $c->query('ServerContainer')->getGroupManager(),
                $c->query('AppService'),
                $c->query('LoggingService')
            );
        });

        /**
         * Register SettingsController
         */
        $container->registerService('SettingsController', function (IContainer $c) {
            return new SettingsController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('Config'),
                $c->query('L10N')
            );
        });

        /**
         * Register AuthenticationController
         */
        $container->registerService('AuthenticationController', function (IContainer $c) {
            return new AuthenticationController(
                $c->query('AppName'),
                $c->query('Request'),
                $c->query('Config'),
                $c->query('UserService'),
                $c->query('AppService'),
                $c->query('ServerContainer')->getUserSession(),
                $c->query('LoggingService')
            );
        });

        /**
         * Register UserHooks
         */
        $container->registerService('UserHooks', function (IContainer $c) {
            return new UserHooks(
                $c->query('AppName'),
                $c->query('ServerContainer')->getUserManager(),
                $c->query('ServerContainer')->getUserSession(),
                $c->query('Config'),
                $c->query('UserService'),
                $c->query('AppService'),
                $c->query('LoggingService'),
                $c->query('Backend')
            );
        });
    }

    public function register(IRegistrationContext $context): void
    {
        // Service registration happens in the constructor for now.
    }

    public function boot(IBootContext $context): void
    {
        $container = $context->getAppContainer();

        if (\OC::$CLI) {
            return;
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        /** @var UserService $userService */
        $userService = $container->query('UserService');

        /** @var AppService $appService */
        $appService = $container->query('AppService');

        // Check for valid setup, only enable app if we have at least a CAS host, port and path
        if ($appService->isSetupValid()) {

            // Register User Backend
            $userService->registerBackend($container->query('Backend'));

            $loginScreen = (strpos($requestUri, '/login') !== false && strpos($requestUri, '/apps/user_cas/login') === false);
            $publicShare = (strpos($requestUri, '/index.php/s/') !== false && $appService->arePublicSharesProtected());

            if ($requestUri === '/' || $loginScreen || $publicShare) {

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // POST is used for single logout requests

                    // Register UserHooks
                    $container->query('UserHooks')->register();

                    // URL params and redirect_url cookie
                    setcookie("user_cas_enforce_authentication", "0", ["path" => "/"]);
                    $urlParams = '';

                    if (isset($_REQUEST['redirect_url'])) {

                        $urlParams = $_REQUEST['redirect_url'];
                        // Save the redirect_rul to a cookie
                        setcookie("user_cas_redirect_url", "$urlParams", ["path" => "/"]);
                    }

                    // Register alternative LogIn
                    $appService->registerLogIn();

                    /** @var boolean $isEnforced */
                    $isEnforced = $appService->isEnforceAuthentication($_SERVER['REMOTE_ADDR'], $requestUri);

                    // Check if public share, if yes, enforce regardless the enforce-flag
                    if ($publicShare) {
                        $isEnforced = true;
                    }

                    // Check for enforced authentication
                    if ($isEnforced && (!isset($_COOKIE['user_cas_enforce_authentication']) || (isset($_COOKIE['user_cas_enforce_authentication']) && $_COOKIE['user_cas_enforce_authentication'] === '0'))) {

                        /** @var LoggingService $loggingService */
                        $loggingService = $container->query("LoggingService");

                        $loggingService->write(LoggingService::DEBUG, 'Enforce Authentication was: ' . $isEnforced);
                        setcookie("user_cas_enforce_authentication", '1', ["path" => "/"]);

                        // Initialize app
                        if (!$appService->isCasInitialized()) {

                            try {

                                $appService->init();

                                //if (!\phpCAS::isAuthenticated()) {

                                $loggingService->write(LoggingService::DEBUG, 'Enforce Authentication was on and phpCAS is not authenticated. Redirecting to CAS Server.');

                                setcookie("user_cas_redirect_url", urlencode($requestUri), ["path" => "/"]);

                                header("Location: " . $appService->linkToRouteAbsolute($container->getAppName() . '.authentication.casLogin'));
                                die();
                                //}

                            } catch (\OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException $e) {

                                $loggingService->write(LoggingService::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());
                            }
                        }
                    }
                }
            } else {

                // Filter DAV requests
                if (strpos($requestUri, '/remote.php') === false && strpos($requestUri, '/webdav') === false && strpos($requestUri, '/dav') === false) {
                    // Register UserHooks
                    $container->query('UserHooks')->register();
                }
            }
        } else {

            $appService->unregisterLogIn();
        }
    }
}
