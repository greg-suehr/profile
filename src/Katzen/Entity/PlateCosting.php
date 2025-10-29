<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\PlateCostingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlateCostingRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_recipe_costing', columns: ['recipe_id'])]
#[ORM\Index(name: 'idx_plate_costing_status', columns: ['cost_status'])]
class PlateCosting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;

    #[ORM\Column(length: 255)]
    private ?string $menu_item_name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4)]
    private ?string $current_cost = '0.0000';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $current_price = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $current_food_cost_pct = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $target_price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $target_food_cost_pct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $target_gp_pct = null;

    #[ORM\Column(length: 50)]
    private ?string $cost_status = 'on_target'; // on_target, warning, critical

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $price_last_updated = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $alert_threshold_pct = '5.00';

    #[ORM\Column]
    private ?bool $alert_enabled = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    public function __construct()
    {
        $this->updated_at = new \DateTime();
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;
        return $this;
    }

    public function getMenuItemName(): ?string
    {
        return $this->menu_item_name;
    }

    public function setMenuItemName(string $menu_item_name): static
    {
        $this->menu_item_name = $menu_item_name;
        return $this;
    }

    public function getCurrentCost(): ?string
    {
        return $this->current_cost;
    }

    public function setCurrentCost(string $current_cost): static
    {
        $this->current_cost = $current_cost;
        $this->recalculateFoodCostPct();
        $this->updateCostStatus();
        return $this;
    }

    public function getCurrentPrice(): ?string
    {
        return $this->current_price;
    }

    public function setCurrentPrice(string $current_price): static
    {
        $this->current_price = $current_price;
        $this->recalculateFoodCostPct();
        $this->updateCostStatus();
        return $this;
    }

    public function getCurrentFoodCostPct(): ?string
    {
        return $this->current_food_cost_pct;
    }

    public function setCurrentFoodCostPct(string $current_food_cost_pct): static
    {
        $this->current_food_cost_pct = $current_food_cost_pct;
        return $this;
    }

    public function getTargetPrice(): ?string
    {
        return $this->target_price;
    }

    public function setTargetPrice(?string $target_price): static
    {
        $this->target_price = $target_price;
        return $this;
    }

    public function getTargetFoodCostPct(): ?string
    {
        return $this->target_food_cost_pct;
    }

    public function setTargetFoodCostPct(?string $target_food_cost_pct): static
    {
        $this->target_food_cost_pct = $target_food_cost_pct;
        $this->updateCostStatus();
        return $this;
    }

    public function getTargetGpPct(): ?string
    {
        return $this->target_gp_pct;
    }

    public function setTargetGpPct(?string $target_gp_pct): static
    {
        $this->target_gp_pct = $target_gp_pct;
        return $this;
    }

    public function getCostStatus(): ?string
    {
        return $this->cost_status;
    }

    public function setCostStatus(string $cost_status): static
    {
        $this->cost_status = $cost_status;
        return $this;
    }

    public function getPriceLastUpdated(): ?\DateTimeInterface
    {
        return $this->price_last_updated;
    }

    public function setPriceLastUpdated(?\DateTimeInterface $price_last_updated): static
    {
        $this->price_last_updated = $price_last_updated;
        return $this;
    }

    public function getAlertThresholdPct(): ?string
    {
        return $this->alert_threshold_pct;
    }

    public function setAlertThresholdPct(string $alert_threshold_pct): static
    {
        $this->alert_threshold_pct = $alert_threshold_pct;
        return $this;
    }

    public function isAlertEnabled(): ?bool
    {
        return $this->alert_enabled;
    }

    public function setAlertEnabled(bool $alert_enabled): static
    {
        $this->alert_enabled = $alert_enabled;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    // ============================================
    // BUSINESS LOGIC
    // ============================================

    private function recalculateFoodCostPct(): void
    {
        $price = (float)$this->current_price;
        if ($price <= 0) {
            $this->current_food_cost_pct = '0.00';
            return;
        }

        $cost = (float)$this->current_cost;
        $pct = ($cost / $price) * 100;
        $this->current_food_cost_pct = number_format($pct, 2, '.', '');
    }

    private function updateCostStatus(): void
    {
        if (!$this->target_food_cost_pct) {
            $this->cost_status = 'on_target';
            return;
        }

        $current = (float)$this->current_food_cost_pct;
        $target = (float)$this->target_food_cost_pct;
        $threshold = (float)$this->alert_threshold_pct;

        $variance = $current - $target;

        if ($variance <= 0) {
            // Under target is good
            $this->cost_status = 'on_target';
        } elseif ($variance <= $threshold) {
            // Within threshold
            $this->cost_status = 'warning';
        } else {
            // Over threshold
            $this->cost_status = 'critical';
        }
    }

    public function getGrossProfit(): float
    {
        $price = (float)$this->current_price;
        $cost = (float)$this->current_cost;
        return $price - $cost;
    }

    public function getGrossProfitPct(): float
    {
        $price = (float)$this->current_price;
        if ($price <= 0) {
            return 0.0;
        }

        return ($this->getGrossProfit() / $price) * 100;
    }

    public function getSuggestedPrice(): float
    {
        if (!$this->target_food_cost_pct) {
            return 0.0;
        }

        $cost = (float)$this->current_cost;
        $targetPct = (float)$this->target_food_cost_pct;
        
        if ($targetPct <= 0) {
            return 0.0;
        }

        return $cost / ($targetPct / 100);
    }

    public function getVarianceFromTarget(): float
    {
        if (!$this->target_food_cost_pct) {
            return 0.0;
        }

        return (float)$this->current_food_cost_pct - (float)$this->target_food_cost_pct;
    }
}
