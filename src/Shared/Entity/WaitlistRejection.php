<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_waitlist_rejection_created_at', columns: ['created_at'])]
class WaitlistRejection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 32)]
        private string $source,
        #[ORM\Column(length: 32)]
        private string $reason,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $emailDomain = null,
        #[ORM\Column(length: 2, nullable: true)]
        private ?string $country = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $userAgent = null,
        #[ORM\Column(nullable: true)]
        private ?bool $turnstilePassed = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $email = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getEmailDomain(): ?string
    {
        return $this->emailDomain;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getTurnstilePassed(): ?bool
    {
        return $this->turnstilePassed;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
