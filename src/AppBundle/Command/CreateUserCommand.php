<?php

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class CreateUserCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:create-user')
            ->setDescription('Create a new user')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Username of the new user'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Email address of the new user'
            )
            ->addArgument(
                'password',
                InputArgument::OPTIONAL,
                'Password for the new user (if not set, will be generated)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        // Vérifier si l'utilisateur existe déjà
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $existingUser = $userManager->findUserByUsername($username);
        $existingEmail = $userManager->findUserByEmail($email);

        if ($existingUser) {
            $output->writeln("<error>User with username '$username' already exists.</error>");
            return 1;
        }
        if ($existingEmail) {
            $output->writeln("<error>User with email '$email' already exists.</error>");
            return 1;
        }

        // Générer un mot de passe aléatoire si non fourni
        if (empty($password)) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+[]{}';
            $password = '';
            for ($i = 0; $i < 12; $i++) {
                $password .= $chars[random_int(0, strlen($chars) - 1)];
            }
        }

        // Création de l'utilisateur
        $user = $userManager->createUser();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $user->setEnabled(true);

        $userManager->updateUser($user);

        $output->writeln(date('c') . " User $username created.");
        $output->writeln("Password: $password");
        return 0;
    }
}