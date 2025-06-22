<?php

namespace App\Helpers;

class Code
{

    public static function generate($len = 5)
    {
        $chr = [
            "A",
            "B",
            "C",
            "D",
            "E",
            "F",
            "G",
            "H",
            "I",
            "J",
            "K",
            "L",
            "M",
            "N",
            "P",
            "Q",
            "R",
            "S",
            "T",
            "U",
            "V",
            "W",
            "X",
            "Y",
            "Z",
            "1",
            "2",
            "3",
            "4",
            "5",
            "6",
            "7",
            "8",
            "9",
        ];
        $hash = "";
        for ($i = 1; $i <= $len; $i++) {
            $hash .= $chr[mt_rand(0, count($chr) - 1)];
        }
        return $hash;
    }
}
