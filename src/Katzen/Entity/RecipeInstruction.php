<?php

namespace App\Katzen\Entity;

use App\Katzen\Repository\RecipeInstructionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipeInstructionRepository::class)]
class RecipeInstruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'recipeInstructions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipe $recipe = null;

    #[ORM\Column]
    private ?int $section_number = 0;

    #[ORM\Column]
    private ?int $step_number = 0;

    #[ORM\Column(length: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $prep_time = '0.0';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $cook_time = '0.0';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private ?string $wait_time = '0.0';

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

    public function getSectionNumber(): ?int
    {
        return $this->section_number;
    }

    public function setSectionNumber(int $section_number): static
    {
        $this->section_number = $section_number;

        return $this;
    }

    public function getStepNumber(): ?int
    {
        return $this->step_number;
    }

    public function setStepNumber(int $step_number): static
    {
        $this->step_number = $step_number;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrepTime(): ?string
    {
        return $this->prep_time;
    }

    public function setPrepTime(string $prep_time): static
    {
        $this->prep_time = $prep_time;

        return $this;
    }

    public function getCookTime(): ?string
    {
        return $this->cook_time;
    }

    public function setCookTime(string $cook_time): static
    {
        $this->cook_time = $cook_time;

        return $this;
    }

    public function getWaitTime(): ?string
    {
        return $this->wait_time;
    }

    public function setWaitTime(string $wait_time): static
    {
        $this->wait_time = $wait_time;

        return $this;
    }
}
