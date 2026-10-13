<?php

namespace App\Console\Commands;

use App\Models\TerminalDevice;
use Illuminate\Console\Command;

class TerminalDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'terminal:devices
        {--revoke= : Revoke the pairing with this id}
        {--revoke-bed= : Revoke every pairing of this bed id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List the bedside terminals paired with beds, or revoke a pairing (e.g. a lost or moved tablet)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($id = $this->option('revoke')) {
            $device = TerminalDevice::whereNull('revoked_at')->find($id);
            if (! $device) {
                $this->error("No active pairing with id {$id}.");

                return self::FAILURE;
            }
            $device->revoke();
            $this->info("Revoked pairing {$id}. That terminal must be paired again.");

            return self::SUCCESS;
        }

        if ($bedId = $this->option('revoke-bed')) {
            $count = TerminalDevice::where('bed_id', $bedId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->info("Revoked {$count} pairing(s) of bed {$bedId}.");

            return self::SUCCESS;
        }

        $devices = TerminalDevice::whereNull('revoked_at')
            ->with(['bed.ward', 'pairedBy'])
            ->orderBy('bed_id')
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No terminals are paired.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Ward', 'Bed', 'Name', 'Paired by', 'Paired at', 'Last seen', 'Last IP'],
            $devices->map(fn(TerminalDevice $d) => [
                $d->id,
                $d->bed?->ward?->ward_name ?? '-',
                $d->bed?->bed_number ?? '-',
                $d->name ?? '-',
                $d->pairedBy?->name ?? '-',
                $d->created_at?->format('Y-m-d H:i'),
                $d->last_seen_at?->format('Y-m-d H:i') ?? 'never',
                $d->last_ip ?? '-',
            ])
        );

        return self::SUCCESS;
    }
}
