<?php

namespace App\Form;

use App\Entity\Recipe;
use Craue\FormFlowBundle\Form\FormFlow;
use Craue\FormFlowBundle\Form\FormFlowInterface;
use App\Form\RecipeBuilderType;

class CreateRecipeFlow extends FormFlow {

	protected function loadStepsConfig() {
		return [
			[
				'label' => 'cover',
				'form_type' => RecipeBuilderType::class,
			],
			[
				'label' => 'ingredients',
				'form_type' => IngredientSelectorType::class,
				'skip' => function($estimatedCurrentStepNumber, FormFlowInterface $flow) {
					return $estimatedCurrentStepNumber > 2;
				},
			],            
            [
				'label' => 'instructions',
				'form_type' => InstructionSelectorType::class,
				'skip' => function($estimatedCurrentStepNumber, FormFlowInterface $flow) {
					return $estimatedCurrentStepNumber > 3;
				},
			],            
			[
				'label' => 'confirmation',
			],
		];
	}

}
