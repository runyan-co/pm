<?php

declare(strict_types=1);

namespace ProcessMaker\Cli;

use Throwable;
use DomainException;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Helper\TableSeparator;
use ProcessMaker\Cli\Facades\SnapshotsRepository as SnapshotsRepositoryFacade;

class SnapshotsRepository
{
    /**
     * @var int
     */
    private static $pid;

    /**
     * @var array
     */
    private $repository = [];

    /**
     * Record and display timings
     *
     * @var bool
     */
    private static $display = false;

    /**
     * Enable timing snapshots
     *
     * @return void
     */
    public static function enable(): void
    {
        static::$display = true;
    }

    /**
     * Set the master PHP process ID
     *
     * @param  int  $pid
     *
     * @return void
     */
    public static function setPid(int $pid): void
    {
        static::$pid = $pid;
    }

    /**
     * Timing snapshots are enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true === static::$display;
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
            if ($previous->microtime === $next->microtime) {
                return 0;
            }

            return $previous->microtime > $next->microtime ? 1 : -1;
        });

        return Collection::make($this->repository)->keyBy(function ($value) {
            return $value->key;
        })->toArray();
    }

    public function __destruct()
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (blank($snapshots = $this->getSnapshots())) {
            return;
        }

        // Only output the stats for the primary PHP process before
        // exiting (instead of any forked child processes)
        if (getmypid() !== SnapshotsRepositoryFacade::getInstance()::$pid) {
            return;
        }

        // Grab the final tallies only for display
        $snapshots = array_filter($snapshots, static function ($value) {
            return Str::contains($value->key, 'final-');
        });

        // Add a table separator and then the total time
        // taken to run all commands cumulatively
        $snapshots[] = new TableSeparator();

        $snapshots[] = (object) [
            'value' => '(all)',
            'time' => $this->getTimeElapsed(),
            'time_in_seconds' => $this->getSecondsElapsed(),
            'time_in_milliseconds' => $this->getMillisecondsElapsed(),
        ];

        $total_time_in_milliseconds = $this->getMillisecondsElapsed();

        $rows = array_map(static function ($row) use ($total_time_in_milliseconds) {
            if ($row instanceof TableSeparator) {
                return $row;
            }

            $time = $row->time;

            if ($row->value === '(all)') {
                $style = 'options=bold,underscore';
            } else if (($seconds = (float) $row->time_in_seconds) <= 5) {
                $style = 'fg=green';
            } elseif ($seconds < 10) {
                $style = 'fg=yellow';
            } else {
                $style = 'fg=red';
            }

            $percent_total = round(($row->time_in_milliseconds / $total_time_in_milliseconds) * 100, 2);

            if ($percent_total < 1.00) {
                $percent_total = "< 1";
            }

            return [
                'command' => $row->value,
                'time' => "<{$style}>{$time}</> or <{$style}>{$row->time_in_milliseconds}ms</>",
                'percent_of_time_total' => "{$percent_total}%",
            ];
        }, $snapshots);

        table(['Command', 'Timing', 'Percent of Total'], $rows);
    }

    /**
     * Start a snapshot
     *
     * @param $value
     * @param  callable  $callable
     *
     * @return mixed
     * @throws \Exception
     */
    public function startSnapshot($value, callable $callable)
    {
        $startKey = $this->takeSnapshot($value);

        $called = $callable();

        $this->stopSnapshot($value, $startKey);

        return $called;
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
        $time_in_milliseconds = $this->getMillisecondsElapsed($start_values->microtime, $stop_values->microtime);
        $final_key = $this->generateSnapshotKey('final');

        $value = Str::limit(trim(Str::replace(PHP_EOL, " ", $value)),  64);
        $value = "<fg=cyan>{$value}</>";

        $this->repository[$final_key] = (object) [
            'key' => $final_key,
            'value' => $value,
            'time' => $time_taken,
            'time_in_seconds' => $time_in_seconds,
            'time_in_milliseconds' => $time_in_milliseconds,
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
        return round(abs(($microtime_start ?? MICROTIME_START) -
            ($microtime_end ?? microtime(true))), $precision);
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
    public function getMillisecondsElapsed(
        float $microtime_start = null,
        float $microtime_end = null): float
    {
        return floor(abs((($microtime_start ?? MICROTIME_START)
                - ($microtime_end ?? microtime(true)))) * 1000);
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

            return "{$hours}h and {$minutes}m";
        }

        if ($minutes >= 1.000000) {
            $seconds = round(abs($minutes - (int) Str::before((string) $minutes, '.')) * 60);
            $minutes = (int) Str::before((string) $minutes, '.');

            return "{$minutes}m and {$seconds}s";
        }

        return ($seconds = round($seconds, 2)) === 0.00 ? '< 1s' : "{$seconds}s";
    }
}
