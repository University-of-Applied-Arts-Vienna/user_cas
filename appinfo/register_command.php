<?php

use OCA\UserCAS\Service\UserService;

/**
 * @var $c \OCP\IContainer
 */
$c = \OC::$server->getAppContainer('user_cas');

//TODO: Umbauen, so dass container wegfällt.

$userService = new UserService(
    $c->query('AppName'),
    $c->query('Config'),
    $c->query('ServerContainer')->getUserManager(),
    $c->query('ServerContainer')->getUserSession(),
    $c->query('ServerContainer')->getGroupManager(),
    $c->query('AppService'),
    $c->query('Backend'),
    $c->query('LoggingService')
);

var_dump($userService);
exit;

$userManager = \OC::$server->getUserManager();
$groupManager = \OC::$server->getGroupManager();
$mailer = \OC::$server->getMailer();


/** @var $application Symfony\Component\Console\Application */
$application->add(new \OCA\UserCas\Command\CreateUser($userService, $userManager, $groupManager, $mailer));