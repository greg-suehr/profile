<?php
namespace App\Adapter;

interface RecipeAdapterInterface
{
    public function supports(string $type): bool;
    public function process(mixed $file);
}
