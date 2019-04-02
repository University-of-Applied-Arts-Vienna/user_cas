<?php
/**
 * Created by PhpStorm.
 * User: felixrupp
 * Date: 02.04.19
 * Time: 20:57
 */

namespace OCA\UserCAS\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;


class CreateUser extends \OC\Core\Command\User\Add
{


    protected function configure()
    {
        $this
            ->setName('usercas:create-user')
            ->setDescription('adds a user')
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
                'q',
                InputOption::VALUE_OPTIONAL,
                'The quota the user should get'
            )
            ->addOption(
                'enabled',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Set User enabled'
            );
    }

    //TODO: Add execute method,
    //TODO: add code to register backend
    //TODO: create a user based on the arguments and options
}