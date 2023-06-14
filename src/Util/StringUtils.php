<?php

declare(strict_types=1);

namespace D2L\DataHub\Util;

class StringUtils
{
    public static function formatElapsedTime(
        float $startTime,
        bool $calcElapsed = true
    ): string {
        $elapsedTime = abs(($calcElapsed) ? microtime(true) - $startTime : $startTime);
        $elapsed     = intval($elapsedTime);
        $hours       = sprintf("%'.02d", intval(gmdate("H", $elapsed)));
        $minutes     = sprintf("%'.02d", intval(gmdate("i", $elapsed)));
        $seconds     = sprintf("%'.02d", intval(gmdate("s", $elapsed)));
        $fraction    = explode(".", number_format(round($elapsedTime - $elapsed, 3), 3))[1];

        return "{$hours}:{$minutes}:{$seconds}.{$fraction}";
    }


    public static function formatElapsedTimeExt(
        float $startTime,
        bool $calcElapsed = true
    ): string {
        $elapsedTime = abs(($calcElapsed) ? microtime(true) - $startTime : $startTime);
        $elapsed = intval($elapsedTime);
        $hours = intval(gmdate("H", $elapsed));
        $minutes = intval(gmdate("i", $elapsed));
        $seconds = intval(gmdate("s", $elapsed));
        $fraction = explode(".", number_format(round($elapsedTime - $elapsed, 3), 3))[1];

        $elapsed = "{$seconds}.{$fraction}s";
        if ($hours > 0 || $minutes > 0) {
            $elapsed = "{$minutes}m, " . $elapsed;
            if ($hours > 0) {
                $elapsed = "{$hours}h, " . $elapsed;
            }
        }

        return $elapsed;
    }
}
