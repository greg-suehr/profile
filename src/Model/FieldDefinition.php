<?php

namespace App\Model;

class FieldDefinition
{
  public string $name = '';
  public string $label = '';
  public string $type = 'text'; // e.g. text, integer, choice, date
  public ?string $options = '{}';  // e.g. ['choices' => [...], 'required'=>true]
}

?>
