<?php

namespace App\Katzen\DataFixtures;

use App\Katzen\Entity\KatzenUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class KatzenUserFixture extends Fixture
{
    public const ADMIN_USER_REFERENCE = 'demo_admin';
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $existing = $manager->getRepository(KatzenUser::class)
            ->findOneBy(['email' => 'admin@katzen.test']);
        
        if ($existing) {
            $user = $existing;
        } else {
            $user = new KatzenUser();
            $user->setUsername('demo_admin');
            $user->setName('Demo Admin');
            $user->setIsVerified(true);
            $user->setLastLogin(new \DateTime);
            $user->setEmail('admin@katzen.test');
        }
        
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'admin123')
        );
        
        $manager->persist($user);
        $manager->flush();
        
        // Store reference for RecipesFixture
        $this->addReference(self::ADMIN_USER_REFERENCE, $user);
    }
}
