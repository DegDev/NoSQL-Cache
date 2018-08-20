<?php
/**
 * @author Dmitri Puscas <degwelloa@gmail.com> 
 */

namespace App\Price;

class PriceFormatter {
    private static $graphem = [
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'RUR' => '&#8381;',
        'JPY' => '¥',
        'KRW' => '₩',
        'INR' => '&#8377;',
        'ILS' => '₪',
        'THB' => '฿',
        'TRY' => '&#8378;',
        'PHP' => '₱',
        'UAH' => '₴',
        'SAR' => '﷼',
        'CNY' => '¥',
    ];
    public static function make($price, $currency) {
        
        if ($currency == 'USD') {
            $formatedPrice = '$'.number_format($price, 2, '.', ',');
        } else {
            
            if(array_key_exists($currency, self::$graphem)){                
                $currency = self::$graphem[$currency];
            }
            $formatedPrice = number_format($price, 2, '.', ',')." {$currency}";
        }

        return $formatedPrice;
    }    
}