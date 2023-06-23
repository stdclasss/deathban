<?php

namespace hcf\deathban\util;

use pocketmine\utils\TextFormat;

final class StringUtils {
    private function __construct() {
    }

    public static function startsWith(string $string, string $prefix): bool {
        return strtolower(substr($string, 0, strlen($prefix))) == strtolower($prefix);
    }

    public static function endsWith(string $string, string $prefix): bool {
        return strtolower(substr($string, -strlen($prefix))) == strtolower($prefix);
    }

    public static function substituteString(string $str, array $args, string $prefix = "{", string $suffix = "}"): string {
        if(count($args) < 1) return $str;
        foreach($args as $item => $value) {
            $str = str_ireplace($prefix . $item . $suffix, $value, $str);
        }

        return $str;
    }

    public static function sanitizeCommand(string $cmdLine): string {
        $cmdLine = preg_replace("/[^A-Za-z0-9 ]/", "", $cmdLine);
        $cmdLine = preg_replace("!\s+!", " ", $cmdLine);
        $cmdLine = stripslashes($cmdLine);
        if(StringUtils::startsWith($cmdLine, "/")) {
            $cmdLine = substr($cmdLine, 1);
        }
        return trim($cmdLine);
    }

    public static function generateRankingList(int $count, string $format): array {
        $ranking = [];
        for($i = 1; $i <= $count; $i++) {
            $ranking[$i] = str_replace("{i}", (string)$i, $format);
        }
        return $ranking;
    }

    public static function minecraftRomanNumerals(int $number): string {
        static $romanNumerals = [
            1 => "I", 2 => "II", 3 => "III", 4 => "IV", 5 => "V",
            6 => "VI", 7 => "VII", 8 => "VII", 9 => "IX", 10 => "X"
        ];
        return $romanNumerals[$number] ?? ((string)$number);
    }

    public static function getInternalKey($anything):string {
        // anything => int / string key
        if(is_object($anything)){
            return spl_object_hash($anything);
        }
        if(is_array($anything)){
            return json_encode($anything);
        }
        return (string)$anything;
    }

    public static function getPingColor(float $latency):string {
        return match (true) {
            $latency < 100 => TextFormat::GREEN,
            $latency < 200 => TextFormat::YELLOW,
            $latency < 300 => TextFormat::GOLD,
            default => TextFormat::RED,
        };
    }

    public static function shuffleStr(string $string):string {
        $arr = str_split($string);
        shuffle($arr);
        return implode("", $arr);
    }
}