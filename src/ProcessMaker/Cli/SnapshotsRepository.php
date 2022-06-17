<?php

namespace ProcessMaker\Cli;

use Throwable;
use DomainException;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

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

    /**
     * @param  string  $action
     *
     * @return string
     * @throws \Exception
     */
    private function generateSnapshotKey(string $action): string
    {
        return $action.'-'.random_int(9999, 99999);
    }

    public function getSnapshots(): array
    {
        usort($this->repository, static function ($previous, $next) {
            return $previous->microtime > $next->microtime;
        });

        return Collection::make($this->repository)->keyBy(function ($value) {
            return $value->key;
        })->toArray();
    }

    public function __destruct()
    {
        if (blank($snapshots = $this->getSnapshots())) {
            return;
        }

        $snapshots = array_filter($snapshots, static function ($value) {
            return Str::contains($value->key, 'final-');
        });

        table(['Command', 'Time Taken'],
            array_map(static function ($snapshot) {
                $time = $snapshot->time;

                if (($seconds = (float) $snapshot->time_in_seconds) <= 5) {
                    $time = "<fg=green>{$time}</>";
                } elseif ($seconds < 10) {
                    $time = "<fg=yellow>{$time}</>";
                } else {
                    $time = "<fg=red>{$time}</>";
                }

                return [
                    'command' => $snapshot->value,
                    'time' => $time
                ];
            }, $snapshots));
    }

    /**
     * Start a snapshot
     *
     * @param $value
     *
     * @return void
     * @throws \Exception
     */
    public function startSnapshot($value): string
    {
        return $this->takeSnapshot($value);
    }

    /**
     * Stop the snapshot
     *
     * @param $value
     * @param $start_key
     *
     * @return void
     * @throws \Exception
     */
    public function stopSnapshot($value, $start_key): void
    {
        $stop_key = $this->takeSnapshot($value, 'stop');

        try {
            $start_values = $this->findSnapshot($start_key);
            $stop_values = $this->findSnapshot($stop_key);
        } catch (Throwable $exception) {
            return;
        }

        $time_in_seconds = $this->getSecondsElapsed($start_values->microtime, $stop_values->microtime);
        $time_taken = $this->getTimeElapsed($start_values->microtime, $stop_values->microtime);
        $final_key = $this->generateSnapshotKey('final');

        $value = Str::limit(trim(Str::replace(PHP_EOL, " ", $value)),  64);
        $value = "<fg=cyan>{$value}</>";

        $this->repository[$final_key] = (object) [
            'key' => $final_key,
            'value' => $value,
            'time' => $time_taken,
            'time_in_seconds' => $time_in_seconds,
            'microtime' => microtime(true),
        ];
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function findSnapshot($key)
    {
        if (!is_string($key) && !is_int($key)) {
            throw new DomainException('A string or integer type key is required to find a snapshot.');
        }

        if (!array_key_exists($key, $snapshots = $this->getSnapshots())) {
            throw new RuntimeException("Snapshot with key not found: {$key}");
        }

        return $snapshots[$key];
    }

    /**
     * Take a snapshot of the elapsed time
     *
     * @param  string|array|object|int|float  $value
     * @param  string  $action
     *
     * @return void
     * @throws \Exception
     */
    public function takeSnapshot($value, string $action = 'start'): string
    {
        if ($action !== 'start') {
            $action = 'stop';
        }

        if (is_object($value) || is_int($value) || is_float($value)) {
            $value = serialize($value);
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        $this->repository[$key = $this->generateSnapshotKey($action)] = (object) [
            'key' => $key,
            'value' => $value,
            'action' => $action,
            'microtime' => microtime(true),
            'formatted_from_start' => $this->getTimeElapsed(),
        ];

        return $key;
    }

    /**
     * Get the difference of microtime elapsed between the provided
     * $microtime and the current or provided microtime end time, converted to
     * seconds as a two-point floating point decimal
     *
     * @param  float|null  $microtime_start
     * @param  float|null  $microtime_end
     * @param  int  $precision
     *
     * @return float
     */
    public function getSecondsElapsed(
        float $microtime_start = null,
        float $microtime_end = null,
        int $precision = 4): float
    {
        return round(abs(($microtime_start ?? $this->microtimeStart) -
            ($microtime_end ?? microtime(true))), $precision);
    }

    /**
     * Returns a formatted string of time elapsed
     *
     * @param  float|null  $start
     * @param  float|null  $stop
     *
     * @return string
     */
    public function getTimeElapsed(float $start = null, float $stop = null): string
    {
        $hours = round(($minutes = round(
            ($seconds = $this->getSecondsElapsed($start, $stop)
        ) / 60, 6)) / 60, 6);

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

        return ($seconds = round($seconds, 2)) === 0.00 ? '< 1s' : "${seconds}s";
    }
}
