<?php
/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
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

namespace OCA\User_CAS\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Http;
use \OCP\AppFramework\Controller;
use \OCP\IGroupManager;
use \OCP\IL10N;
use \OCP\IConfig;
use \OCP\IUser;
use \OCP\IURLGenerator;

use \OCA\User_CAS\Service\UserService;


/**
 * Class AuthenticationController
 *
 * @package OCA\User_CAS\Controller
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4
 */
class AuthenticationController
{

    private $l10n;
    private $config;
    private $userService;

    protected $appName;

    public function __construct($appName, IRequest $request, IConfig $config, UserService $userService)
    {
        $this->config = $config;
        $this->appName = $appName;
        $this->userService = $userService;
        parent::__construct($appName, $request);
    }


    public function login()
    {

        /*$ocUserCas = \OCA\User_CAS\UserCAS::getInstance();

        if ($ocUserCas->isInitialized()) {

            \phpCAS::forceAuthentication();

            $userName = \phpCAS::getUser();

            $isLoggedIn = $this->userService->login($userName, NULL);

            if ($isLoggedIn) {


            }

            /*if (isset($_SERVER["QUERY_STRING"]) && !empty($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] !== 'app=user_cas') {
                header('Location: ' . \OC::$WEBROOT . '/?' . $_SERVER["QUERY_STRING"]);
                exit();
            }*/
        #}

        #\OC::$REQUESTEDAPP = '';
        #\OC_Util::redirectToDefaultPage();
    }
}