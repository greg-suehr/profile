<?php

namespace App\Katzen\Service\Import;

use App\Katzen\Entity\Vendor;
use App\Katzen\Repository\VendorRepository;

use App\Katzen\Service\Import\ReceiptHeaderFooterExtractor;

use App\Katzen\Service\Response\ServiceResponse;
use Doctrine\ORM\EntityManagerInterface;

final class VendorIdentificationService
{
   public function __construct(
     private VendorRepository $vendors,
     private ReceiptHeaderFooterExtractor $extractor,     
     private EntityManagerInterface $em,    
  )
  {}

  /**
   * Evaluate an array of text data (for example, from an OCR receipt scanning service)
   * against a series of meticulously crafted regular expressions and database queries
   * in our valiant effort to preclude the need for any user to ever enter data via
   * keyboard, mouse, or any other mechanical device.
   *   
   * @param array<int,array{text:string,avg_conf?:float}> $lines
   * @retunr array<> $result
   */
  public function identify(array $lines): array
  {
    $signals = [];
    foreach ($lines as $line) {
      $signals = array_merge($signals, $this->extractor->extract($line['text'] ?? ''));
    }

    $candidates = []; // vendor_id => ['score'=>float,'reasons'=>[]]
    $add = function(int $vendorId, float $points, string $reason) use (&$candidates) {
            $candidates[$vendorId]['score'] = ($candidates[$vendorId]['score'] ?? 0) + $points;
            $candidates[$vendorId]['reasons'][] = $reason;
        };
    
    // Weights (hand-crafted, i.e. if these aren't working PLEASE make them better - use science [I didn't])
    $W = [
      'phone'        => 6.0,
      'url'          => 5.0,
      'email'        => 4.0,
      'tax_id'       => 7.0,
      'address_hash' => 3.0,
      'postal'       => 1.5,
      'alias'        => 2.0,
      'brand_token'  => 1.5,
      'store_number' => 1.0,
    ];
    
    foreach ($signals as $sig) {
      switch ($sig->type) {
      case 'phone':
        foreach ($this->vendors->findByE164Suffix($sig->data['e164ish']) as $v) {
          $add($v->getId(), $W['phone'], "phone:{$sig->data['e164ish']}");
        }
        break;
        
      case 'url':
        foreach ($this->vendors->findByDomain($sig->data['domain']) as $v) {
          $add($v->getId(), $W['url'], "domain:{$sig->data['domain']}");
        }
        break;
        
      case 'email':
        $dom = substr(strrchr($sig->data['email'], '@'), 1);
        foreach ($this->vendors->findByDomain($dom) as $v) {
          $add($v->getId(), $W['email'], "email-domain:$dom");
        }
        break;
        
      case 'tax_id':
        foreach ($this->vendors->findByTaxId($sig->data['value']) as $v) {
          $add($v->getId(), $W['tax_id'], "tax_id:{$sig->data['value']}");
        }
        break;
        
      case 'address':
        foreach ($this->vendors->findByHash($sig->data['hash']) as $v) {
          $add($v->getId(), $W['address_hash'], 'address-hash');
        }
        if (!empty($sig->data['postal'])) {
          foreach ($this->vendors->findByPostal($sig->data['postal']) as $v) {
            $add($v->getId(), $W['postal'], "postal:{$sig->data['postal']}");
          }
        }
        break;
        
      case 'brand_token':
        foreach ($this->vendors->findByAlias($sig->data['token']) as $v) {
          $add($v->getId(), $W['alias'], "alias:{$sig->data['token']}");
        }
        break;
        
      default:      
        break;
      }
    }
    
    if (!$candidates) {
      # sad
      return [
        'vendor' => null,
        'confidence' => 0.0,
        'candidates' => [],
        'signals' => $signals,
      ];
    }

    # Boost for multiple distinct signal types matching the same vendor
    $byVendorTypes = [];
    foreach ($signals as $sig) {
      foreach ($candidates as $vendorId => $_) {
        # TODO: better math, but it should go something like:
        #       - track hits-per-type per vendor
        #       - which vendor matched which signal?
      }
    }

    $scores = array_column($candidates, 'score');
    arsort($scores);
    uasort($candidates, fn($a,$b) => $b['score'] <=> $a['score']);

    $topScore = reset($candidates)['score'];
    $second = (count($candidates) > 1) ? array_values($candidates)[1]['score'] : 0.0;
    $margin = $topScore - $second;
    $confidence = 1 - exp(-max(0.0, $margin) / max(1.0, $topScore));

    $topVendorId = (int) array_key_first($candidates);
    $topVendor = $this->vendors->find($topVendorId);

    return [
      'vendor'     => $topVendor,
      'confidence' => round($confidence, 3),
      'candidates' => array_map(fn($vid,$d)=>['vendor_id'=>$vid,'score'=>$d['score'],'reasons'=>$d['reasons']], array_keys($candidates), $candidates),
      'signals'    => $signals,
    ];
  }
}
  
  
