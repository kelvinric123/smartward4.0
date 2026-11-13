<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Made-up figures for showing a board (the command centre's demo modes): no
 * data is saved, each figure follows from the date and what it is, so a board
 * shows the same demo all day, and a different one tomorrow.
 */
final class DemoDay
{
    /**
     * Steady made-up numbers from 0 to 1, the same for the same topic, day and
     * question. Without a day, the same every day: a ward's own character.
     *
     * @return \Closure(string): float
     */
    public static function random(string $topic, ?CarbonInterface $day = null): \Closure
    {
        $date = $day ? $day->format('Y-m-d') : 'always';

        return fn (string $what) => (crc32("{$topic}|{$date}|{$what}") % 100000) / 100000;
    }

    /** A made-up moment of the day, its hour drawn by $hours' 24 weights. */
    public static function moment(CarbonInterface $day, \Closure $random, array $hours, int|string $i): CarbonInterface
    {
        $pick = $random("hour.{$i}") * array_sum($hours);
        $hour = 0;
        while ($hour < 23 && $pick >= $hours[$hour]) {
            $pick -= $hours[$hour++];
        }

        return $day->copy()->startOfDay()->setTime($hour, (int) floor($random("minute.{$i}") * 60), (int) floor($random("second.{$i}") * 60));
    }
}
