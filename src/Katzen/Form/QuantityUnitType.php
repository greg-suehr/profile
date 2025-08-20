<?php

namespace App\Katzen\Form;

use App\Katzen\Entity\Unit;
use App\Katzen\Repository\UnitRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuantityUnitType extends AbstractType
{
  public function __construct(private UnitRepository $units) {}

  public function buildForm(FormBuilderInterface $builder, array $opts): void
  {
      $builder->setInheritData(true)
          ->add('qstr', TextType::class, [
            'mapped' => false,            
            'required' => false,
            'attr' => ['placeholder' => 'e.g. 1 1/2 c', 'class' => 'js-qstr form-control form-control-sm', 'style' => 'width:12ch'],
          ])
          ->add('quantity', HiddenType::class, [
            'required' => false,
            'property_path' => 'quantity'
          ])
          ->add('unit', EntityType::class, [
            'class' => Unit::class,
            'choice_label' => 'name',
            'required' => false,
            'property_path' => 'unit',
            'attr' => ['class' => 'd-none'],
          ]);

      $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $e) {
          $data = $e->getData() ?? [];
          $form = $e->getForm();
          $qstr = (string)($data['qstr'] ?? '');
          
          if ($qstr !== '') {
            [$qty, $unitText] = $this->parseQstr($qstr);
            
            if ($qty !== null) {
              $data['quantity'] = $qty;
            }
            
            if ($unitText) {
              $unit = $this->resolveUnit($unitText);
              if ($unit) {
                $data['unit'] = $unit->getId();
              } else {
                $form->get('qstr')->addError(new FormError(sprintf('Unknown unit: "%s"', $unitText)));
              }
            }

            $e->setData($data);
          }
        });
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults(['inherit_data' => true]);
    }

    // TODO: move to UnitConversion service  
    private function parseQstr(string $s): array
    {
        $s = trim(mb_strtolower(preg_replace('/\s+/', ' ', $s)));
        if ($s === '') return [null, null];

        if (preg_match('/\b(pinch|dash|to taste)\b/', $s, $m)) return [0.0, $m[1]];
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)\s*([a-zμ.% ]+)?$/i', $s, $m)) {
            $val = (int)$m[1] + ((int)$m[2]/(int)$m[3]);
            return [$val, isset($m[4]) ? trim($m[4]) : null];
        }
        if (preg_match('/^(\d+)\/(\d+)\s*([a-zμ.% ]+)?$/i', $s, $m)) {
            $val = (int)$m[1]/(int)$m[2];
            return [$val, isset($m[3]) ? trim($m[3]) : null];
        }
        if (preg_match('/^([+-]?\d+(?:\.\d+)?)\s*([a-zμ.% ]+)?$/i', $s, $m)) {
            return [floatval($m[1]), isset($m[2]) ? trim($m[2]) : null];
        }
        return [null, null];
    }

     private static function mapUnitTextToId(string $u): ?int {
    $u = strtolower($u);
    static $map = [
      'g' => 4,
      'mg' => 5,
      'grn' => 6,
      'scr' => 7,
      'drm' => 8,
      'oz' => 9,
      'lb' => 10,
      'kg' => 11,
      'ml' => 12,
      'l' => 13,
      'tps' => 14,
      'tbs' => 15,
      'floz' => 16,
      'cup' => 17,
      'pt' => 18,
      'qt' => 19,
      'gal' => 20,
      'srv' => 21,
      'ea' => 22,
      'pc' => 23,
      'dz' => 24,
      'mm' => 25,
      'cm' => 26,
      'in' => 27,
      'ft' => 28,
      'sqcm' => 29,
      'sqin' => 30,
      'sqft' => 31,
      's' => 32,
      'm' => 33,
      'lg' => 34,
      'xl' => 35,
      'xxl' => 36,
      'clv' => 38,
      'slc' => 39,
      'pch' => 40,
      'qsp' => 40,
      'rcp' => 41,
      'flt' => 42,
    ];
    return $map[$u] ?? null;
  }

    private function resolveUnit(string $u): ?Unit
    {
        $canon = $this->canonUnit($u);
        return $canon ? $this->units->findOneByCodeOrSynonym($canon) : null;
    }

    private function canonUnit(string $u): ?string
    {
        $x = strtolower(preg_replace('/[.\s]+/','',$u));
        return [
            'tsp'=>'tsp','ts'=>'tsp','t'=>'tsp','teaspoon'=>'tsp',
            'tbsp'=>'tbsp','tbs'=>'tbsp','tbl'=>'tbsp','tablespoon'=>'tbsp',
            'c'=>'cup','cup'=>'cup','floz'=>'floz','fluidounce'=>'floz',
            'oz'=>'oz','lb'=>'lb','lbs'=>'lb','g'=>'g','kg'=>'kg','ml'=>'ml','l'=>'l',
            'pt'=>'pt','qt'=>'qt','gal'=>'gal','ea'=>'ea','pc'=>'pc','dz'=>'dz',
            'pinch'=>'pinch','dash'=>'dash','totaste'=>'to taste'
        ][$x] ?? $x;
    }
}
