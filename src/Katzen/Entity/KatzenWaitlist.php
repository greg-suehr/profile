<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\KatzenWaitlistRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KatzenWaitlistRepository::class)]
class KatzenWaitlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $session_id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $business_name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $business_type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $biggest_challenge = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $software_expectations = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $current_tools = null;

    #[ORM\Column(nullable: true)]
    private ?bool $is_beta = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $preferred_contact_method = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $questionnaire_completed_at = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referral_source = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $utm_campaign = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->session_id;
    }

    public function setSessionId(?string $session_id): static
    {
        $this->session_id = $session_id;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->business_name;
    }

    public function setBusinessName(?string $business_name): static
    {
        $this->business_name = $business_name;

        return $this;
    }

    public function getBusinessType(): ?string
    {
        return $this->business_type;
    }

    public function setBusinessType(?string $business_type): static
    {
        $this->business_type = $business_type;

        return $this;
    }

    public function getBiggestChallenge(): ?string
    {
        return $this->biggest_challenge;
    }

    public function setBiggestChallenge(?string $biggest_challenge): static
    {
        $this->biggest_challenge = $biggest_challenge;

        return $this;
    }

    public function getSoftwareExpectations(): ?string
    {
        return $this->software_expectations;
    }

    public function setSoftwareExpectations(?string $software_expectations): static
    {
        $this->software_expectations = $software_expectations;

        return $this;
    }

    public function getCurrentTools(): ?string
    {
        return $this->current_tools;
    }

    public function setCurrentTools(?string $current_tools): static
    {
        $this->current_tools = $current_tools;

        return $this;
    }

    public function isBeta(): ?bool
    {
        return $this->is_beta;
    }

    public function setIsBeta(?bool $is_beta): static
    {
        $this->is_beta = $is_beta;

        return $this;
    }

    public function getPreferredContactMethod(): ?string
    {
        return $this->preferred_contact_method;
    }

    public function setPreferredContactMethod(?string $preferred_contact_method): static
    {
        $this->preferred_contact_method = $preferred_contact_method;

        return $this;
    }

    public function getQuestionnaireCompletedAt(): ?\DateTimeImmutable
    {
        return $this->questionnaire_completed_at;
    }

    public function setQuestionnaireCompletedAt(?\DateTimeImmutable $questionnaire_completed_at): static
    {
        $this->questionnaire_completed_at = $questionnaire_completed_at;

        return $this;
    }

    public function getReferralSource(): ?string
    {
        return $this->referral_source;
    }

    public function setReferralSource(?string $referral_source): static
    {
        $this->referral_source = $referral_source;

        return $this;
    }

    public function getUtmCampaign(): ?string
    {
        return $this->utm_campaign;
    }

    public function setUtmCampaign(?string $utm_campaign): static
    {
        $this->utm_campaign = $utm_campaign;

        return $this;
    }
}
