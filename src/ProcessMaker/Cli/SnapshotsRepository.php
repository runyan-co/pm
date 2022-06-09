<?php

namespace ProcessMaker\Cli;

use Illuminate\Support\Str;

class SnapshotsRepository
{
    /**
     * @var float
     */
    private $microtimeStart;

    /**
     * @var array
     */
    private $repository = [];

    public function __construct()
    {
        $this->microtimeStart = microtime(true);
    }

    public function getSnapshots(): array
    {
        usort($this->repository, static function ($previous, $next) {
            return $previous->microtime > $next->microtime;
        });

        return $this->repository;
    }

    public function __destruct()
    {
        if (blank($this->repository)) {
            return;
        }

        table(['Name', 'Microtime', 'Time Elapsed Since Start', 'Seconds Elapsed Since Start'],
            array_map(static function ($value) {
                $value->key = Str::limit(trim(Str::remove(PHP_EOL, $value->key)), 32);
                return (array) $value;
            }, $this->repository)
        );
    }

    /**
     * Take a snapshot of the elapsed time
     *
     * @param  string|array|object|int|float $value
     *
     * @return void
     */
    public function takeSnapshot($value): void
    {
        if (is_object($value) || is_int($value) || is_float($value)) {
            $value = serialize($value);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $this->repository[] = (object) [
            'key' => $value,
            'microtime' => microtime(true),
            'formatted_from_start' => $this->getTimeElapsed(),
            'seconds_elapsed_from_start' => $this->getSecondsElapsed(null, 10),
        ];
    }

    /**
     * Get the difference of microtime elapsed between the provided
     * $microtime and the current microtime, converted to
     * seconds as a two-point floating point decimal
     *
     * @param  float|null  $microtime
     * @param  int  $precision
     *
     * @return float
     */
    public function getSecondsElapsed(float $microtime = null, int $precision = 4): float
    {
        return round(abs($microtime ?? $this->microtimeStart - microtime(true)), $precision);
    }

    /**
     * Returns the timing (in seconds) since the
     * CommandLine class was instantiated
     */
    public function getTimeElapsed(): string
    {
        $seconds = $this->getSecondsElapsed();
        $minutes = round($seconds / 60, 6);
        $hours = round($minutes / 60, 6);

        if ($hours >= 1.000000) {
            $minutes = round(abs($seconds - (int) Str::before((string) $seconds, '.')) * 60, 2);
            $hours = (int) Str::before((string) $hours, '.');

            return "${hours}h and ${minutes}m";
        }

        if ($minutes >= 1.000000) {
            $seconds = round(abs($minutes - (int) Str::before((string) $minutes, '.')) * 60);
            $minutes = (int) Str::before((string) $minutes, '.');

            return "${minutes}m and ${seconds}s";
        }

        $seconds = round($seconds, 2);

        return $seconds === 0.00 ? '< 1s' : "${seconds}s";
    }
}
