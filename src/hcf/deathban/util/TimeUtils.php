<?php

namespace hcf\deathban\util;

use DateTime;

final class TimeUtils {
    private function __construct() { }

    public static function secsToHHMMSS(int $seconds, bool $strict = false): string {
        if(!$strict && $seconds < 60 * 60)return self::secsToMMSS($seconds);
        return sprintf('%02d:%02d:%02d', ($seconds / 3600), ($seconds / 60 % 60), $seconds % 60);
    }

    public static function secsToMMSS(float $seconds): string {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds - ($minutes * 60);

        $formattedMinutes = sprintf('%02d', $minutes);
        $formattedSeconds = number_format($remainingSeconds, 2);

        return (int)$formattedMinutes . ':' . (int)$formattedSeconds;
    }

    public static function parseDuration(string $argument): int {
        if(self::isInfinite($argument)) {
            return -1; // -1 = infinite
        }
        $parts = str_split($argument);
        static $time_units = [
            "y" => "year",
            "M" => "month",
            "w" => "week",
            "d" => "day",
            "h" => "hour",
            "m" => "minute",
            "s" => "second"
        ];
        $time = "";
        $i = -1;
        foreach($parts as $part) {
            $i++;
            if(isset($time_units[$part])) {
                $unit = $time_units[$part];
                $n = implode("", array_slice($parts, 0, $i));
                $time .= "$n $unit ";
                array_splice($parts, 0, $i + 1);
                $i = -1;
            }
        }
        $time = trim($time);

        return empty($time) ? 0 : strtotime($time) - time();
    }

    private static function isInfinite(string $str): bool {
        $str = strtolower($str);
        return in_array($str, ["-1", "inf", "infinite", "infinity", "forever", "permanent"], true);
    }

    public static function isDurationValid(string $duration): bool {
        return preg_match("/^(?:\d+[yMwdhms])+$/", $duration) || self::isInfinite($duration);
    }

    public static function timestamp2Readable(int $time): string {
        return date("F j, Y, g:i a T", $time);
    }

    public static function timestamp2Compact(int $time): string {
        return date("j/n/Y g:i:sA T", $time);
    }

    public static function timestamp2HHMMSS(int $time): string {
        return date("H:i:s", $time);
    }

    public static function humanizeDuration(int $seconds): string {
        $diff = (new DateTime("@0"))->diff(new DateTime("@$seconds"));
        $nega = $seconds < 0;
        $seconds = abs($seconds);
        $fmt = "%s seconds";
        if($seconds >= 60) {
            $fmt = "%i minutes and " . $fmt;
        }
        if($seconds >= 60 * 60) {
            $fmt = "%h hours, " . $fmt;
        }
        if($seconds >= 24 * 60 * 60) {
            $fmt = "%a days, " . $fmt;
        }
        return $diff->format($fmt) . ($nega ? " ago" : "");
    }
}