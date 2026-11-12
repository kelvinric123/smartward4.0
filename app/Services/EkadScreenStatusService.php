<?php

namespace App\Services;

use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * What SEEKINK's own panel knows about the e-ink screens: each screen's
 * battery, Bluetooth signal, online state and last report (/device/label/list),
 * and the paint jobs it ran with their result codes (/device/task/list).
 * Read with the same token as the pushes, and matched to the bed mappings
 * by MAC address.
 */
class EkadScreenStatusService
{
    /** The SEEKINK panel shows a battery in red at this level or below */
    public const LOW_BATTERY = 20;

    /** Paint jobs read for each screen's last paint; older ones show under History */
    public const RECENT_TASKS = 500;

    /** busi_publish_status */
    public const TASK_STATUSES = [0 => 'Publishing', 1 => 'Painted', 2 => 'Failed'];

    /** busi_task_code, the step a paint job last reported */
    public const TASK_CODES = [
        101 => 'Failed to retrieve flash address',
        102 => 'Failed to connect to FTP server',
        103 => 'Failed to retrieve file size',
        104 => 'Failed to download file',
        105 => 'Failed to add device to list',
        106 => 'Command added',
        107 => 'File or image downloaded',
        109 => 'Bluetooth wake-up failed',
        110 => 'Bluetooth connection timed out',
        112 => 'Command execution failed',
        113 => 'Executed successfully',
        114 => 'Failed to send file',
        115 => 'STA failed to retrieve flash address',
        116 => 'STA failed to retrieve file size',
        117 => 'STA failed to add device to list',
        118 => 'STA failed to send file',
        205 => 'Authentication failed',
        255 => 'Download successful',
    ];

    protected EkadConfiguration $config;
    protected EkadService $ekad;

    public function __construct(?EkadConfiguration $config = null)
    {
        $this->config = $config ?? EkadConfiguration::getActive() ?? new EkadConfiguration();
        // Shares the config, so a token it renews is the one used here
        $this->ekad = new EkadService($this->config);
    }

    /**
     * Every bed mapping with its screen's state, the screens SEEKINK has that
     * no bed is mapped to, and the counts for the summary
     */
    public function report(): array
    {
        $screens = collect($this->screens())->keyBy(fn ($screen) => EkadBedMapping::formatMac($screen['labelMac'] ?? ''));
        $lastTasks = collect($this->tasks(['pageSize' => self::RECENT_TASKS]))
            ->groupBy(fn ($task) => EkadBedMapping::formatMac($task['labelMac'] ?? ''))
            ->map(fn ($tasks) => $tasks->first());

        $mappings = EkadBedMapping::with('bed.ward')->get()
            ->sortBy(fn ($mapping) => [$mapping->bed?->ward?->ward_name, $mapping->bed?->bed_number]);

        $rows = $mappings->map(function (EkadBedMapping $mapping) use ($screens, $lastTasks) {
            $mac = EkadBedMapping::formatMac($mapping->mac_address);

            return [
                'mapping_id' => $mapping->id,
                'mapping_active' => (bool) $mapping->is_active,
                'ward' => $mapping->bed?->ward?->ward_name,
                'bed' => $mapping->bed?->bed_display_name ?? $mapping->bed?->bed_number,
            ] + $this->screenRow($mac, $screens->get($mac), $lastTasks->get($mac));
        })->values();

        $mapped = $rows->pluck('mac')->all();
        $unmapped = $screens->except($mapped)
            ->map(fn ($screen, $mac) => $this->screenRow($mac, $screen, $lastTasks->get($mac)))
            ->sortBy('nickname')
            ->values();

        $found = $rows->where('found', true);

        return [
            'fetched_at' => now()->format('Y-m-d H:i:s'),
            'summary' => [
                'mapped' => $rows->count(),
                'online' => $found->where('online', true)->count(),
                'offline' => $found->where('online', false)->count(),
                'low_battery' => $found->where('low_battery', true)->count(),
                'paint_failed' => $rows->where('last_paint.failed', true)->count(),
                'missing' => $rows->where('found', false)->count(),
                'unmapped' => $unmapped->count(),
                'seekink_screens' => $screens->count(),
            ],
            'screens' => $rows,
            'unmapped' => $unmapped,
            'low_battery_level' => self::LOW_BATTERY,
        ];
    }

    /**
     * A screen's latest paint jobs, newest first
     */
    public function paintHistory(string $mac, int $count = 10): array
    {
        return collect($this->tasks(['labelMac' => EkadBedMapping::formatMac($mac), 'pageSize' => $count]))
            ->map(fn ($task) => $this->paint($task))
            ->values()
            ->all();
    }

    /**
     * Every screen registered in SEEKINK
     */
    public function screens(): array
    {
        $screens = [];
        $page = 1;
        do {
            $result = $this->get('/device/label/list', ['pageNum' => $page, 'pageSize' => 500]);
            $rows = $result['rows'] ?? [];
            array_push($screens, ...$rows);
            $page++;
        } while ($rows && count($screens) < ($result['total'] ?? 0) && $page <= 20);

        return $screens;
    }

    /**
     * Paint jobs, newest first
     */
    public function tasks(array $query = []): array
    {
        return $this->get('/device/task/list', ['pageNum' => 1] + $query)['rows'] ?? [];
    }

    protected function screenRow(string $mac, ?array $screen, ?array $task): array
    {
        $battery = isset($screen['power']) ? (int) $screen['power'] : null;
        $signal = isset($screen['bluetoothSignal']) ? (int) $screen['bluetoothSignal'] : null;

        return [
            'mac' => $mac,
            'found' => $screen !== null,
            'nickname' => $screen['nickname'] ?? null,
            'online' => isset($screen['onlineStatus']) ? (int) $screen['onlineStatus'] === 1 : null,
            'battery' => $battery,
            'low_battery' => $battery !== null && $battery <= self::LOW_BATTERY,
            'signal' => $signal,
            'signal_level' => $this->signalLevel($signal),
            'firmware' => $screen['labelVersion'] ?? null,
            'model' => trim(($screen['screenSize'] ?? '') . '" ' . ($screen['resolution'] ?? '') . ' ' . ($screen['color'] ?? '')) ?: null,
            'last_report' => $screen['receiveTime'] ?? null,
            'last_report_ago' => $this->ago($screen['receiveTime'] ?? null),
            'last_paint' => $task ? $this->paint($task) : null,
        ];
    }

    protected function paint(array $task): array
    {
        $status = isset($task['status']) ? (int) $task['status'] : null;
        $code = isset($task['errorcode']) ? (int) $task['errorcode'] : null;
        $at = $task['receiveTime'] ?? $task['createTime'] ?? null;

        return [
            'status' => $status,
            'status_label' => self::TASK_STATUSES[$status] ?? 'Unknown',
            'failed' => $status === 2,
            'code' => $code,
            'code_label' => $code !== null ? (self::TASK_CODES[$code] ?? "Code {$code}") : null,
            'sent_at' => $task['createTime'] ?? null,
            'at' => $at,
            'ago' => $this->ago($at),
        ];
    }

    /**
     * Bluetooth RSSI in dBm: above -70 is good, down to -85 fair, below that weak
     */
    protected function signalLevel(?int $rssi): ?string
    {
        if ($rssi === null || $rssi === 0) {
            return null;
        }

        return $rssi > -70 ? 'good' : ($rssi >= -85 ? 'fair' : 'weak');
    }

    /**
     * SEEKINK's times are the server's local time, like the app's
     */
    protected function ago(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        try {
            return Carbon::parse($time, config('app.timezone'))->diffForHumans();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * GET a SEEKINK admin route, logging in again once if the token has expired
     */
    protected function get(string $path, array $query, bool $retried = false): array
    {
        $token = $this->ekad->getToken();
        if (! $token) {
            throw new RuntimeException('Could not log in to SEEKINK. Check Configuration & Login.');
        }

        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->get(rtrim($this->config->base_url, '/') . $path, $query);

        $result = $response->json() ?? [];
        $code = $result['code'] ?? $response->status();

        if ($code === 401 && ! $retried) {
            $this->config->clearToken();

            return $this->get($path, $query, true);
        }

        if ($code !== 200) {
            throw new RuntimeException('SEEKINK answered ' . $path . ' with ' . ($result['msg'] ?? 'HTTP ' . $response->status()));
        }

        return $result;
    }
}
