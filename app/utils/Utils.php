<?php

namespace App\Utils;

use Nette;

class Utils {

    /**
     * Calculates how much percent of $b is $a
     * @param $a
     * @param $b
     * @return float|int
     */
    public static function percentages($a, $b) {
        return ($b > 0 ? ($a / $b) * 100 : 0);
    }
}