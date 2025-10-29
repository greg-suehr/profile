<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\PriceAlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceAlertRepository::class)]
class PriceAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?StockTarget $stock_target = null;

    #[ORM\Column(length: 50)]
    private ?string $alert_type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $threshold_value = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $last_triggered_at = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $last_price = null;

    #[ORM\Column]
    private ?int $trigger_count = 0;

    #[ORM\Column(nullable: true)]
    private ?array $notify_users = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notify_email = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getStockTarget(): ?StockTarget
    {
        return $this->stock_target;
    }

    public function setStockTarget(?StockTarget $stock_target): static
    {
        $this->stock_target = $stock_target;

        return $this;
    }

    public function getAlertType(): ?string
    {
        return $this->alert_type;
    }

    public function setAlertType(string $alert_type): static
    {
        $this->alert_type = $alert_type;

        return $this;
    }

    public function getThresholdValue(): ?string
    {
        return $this->threshold_value;
    }

    public function setThresholdValue(?string $threshold_value): static
    {
        $this->threshold_value = $threshold_value;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastTriggeredAt(): ?\DateTimeInterface
    {
        return $this->last_triggered_at;
    }

    public function setLastTriggeredAt(?\DateTimeInterface $last_triggered_at): static
    {
        $this->last_triggered_at = $last_triggered_at;

        return $this;
    }

    public function getLastPrice(): ?string
    {
        return $this->last_price;
    }

    public function setLastPrice(string $last_price): static
    {
        $this->last_price = $last_price;

        return $this;
    }

    public function getTriggerCount(): ?int
    {
        return $this->trigger_count;
    }

    public function setTriggerCount(int $trigger_count): static
    {
        $this->trigger_count = $trigger_count;

        return $this;
    }

    public function getNotifyUsers(): ?array
    {
        return $this->notify_users;
    }

    public function setNotifyUsers(?array $notify_users): static
    {
        $this->notify_users = $notify_users;

        return $this;
    }

    public function getNotifyEmail(): ?string
    {
        return $this->notify_email;
    }

    public function setNotifyEmail(string $notify_email): static
    {
        $this->notify_email = $notify_email;

        return $this;
    }
}
