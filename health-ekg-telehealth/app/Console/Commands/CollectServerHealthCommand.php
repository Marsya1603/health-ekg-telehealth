<?php

namespace App\Console\Commands;

use App\Models\ServiceStatus;
use App\Models\SystemEvent;
use App\Services\StatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Mengumpulkan metrik server yang benar-benar tersedia dari sistem operasi
 * (bukan angka statis) dan menyimpannya ke cache untuk dashboard Server Health.
 *
 * Jalankan melalui scheduler Laravel setiap menit:
 *   php artisan schedule:work  (pengembangan)
 *   cron: * * * * * php /path/artisan schedule:run >> /dev/null 2>&1
 */
class CollectServerHealthCommand extends Command
{
    protected $signature = 'app:collect-server-health';

    protected $description = 'Mengumpulkan metrik server aktual (CPU, memori, disk, layanan) untuk dashboard Server Health';

    public function handle(): int
    {
        $data = [
            'collected_at' => now()->toIso8601String(),
            'cpu_percent' => $this->cpuPercent(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'uptime_seconds' => $this->uptimeSeconds(),
            'services' => StatusService::all(),
        ];

        Cache::put('server-health', $data, now()->addMinutes(5));
        ServiceStatus::heartbeat('scheduler', 'aktif', 'app:collect-server-health selesai');

        $this->info('Server health dikumpulkan: CPU '.($data['cpu_percent'] ?? '-').'%, RAM '.($data['memory']['used_percent'] ?? '-').'%, Disk '.($data['disk']['used_percent'] ?? '-').'%');

        return self::SUCCESS;
    }

    private function cpuPercent(): ?float
    {
        // Dua sampel /proc/stat untuk menghitung utilisasi CPU nyata (Linux).
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $sample = function (): array {
            $line = (string) file_get_contents('/proc/stat');
            $parts = preg_split('/\s+/', trim(explode("\n", $line)[0]));
            // parts[0] = 'cpu', sisanya: user nice system idle iowait irq softirq steal ...
            $values = array_map('intval', array_slice($parts, 1, 8));

            return [array_sum($values), $values[3] + ($values[4] ?? 0)];
        };

        [$t1, $i1] = $sample();
        usleep(200000);
        [$t2, $i2] = $sample();

        $total = $t2 - $t1;
        $idle = $i2 - $i1;

        if ($total <= 0) {
            return null;
        }

        return round((1 - ($idle / $total)) * 100, 1);
    }

    private function memory(): array
    {
        if (! is_readable('/proc/meminfo')) {
            return [];
        }

        $info = [];
        foreach (file('/proc/meminfo') as $line) {
            $parts = explode(':', $line);
            if (count($parts) === 2) {
                $info[trim($parts[0])] = (int) filter_var($parts[1], FILTER_SANITIZE_NUMBER_INT); // kB
            }
        }

        $total = $info['MemTotal'] ?? 0;
        $available = $info['MemAvailable'] ?? 0;

        if ($total <= 0) {
            return [];
        }

        return [
            'total_mb' => round($total / 1024),
            'available_mb' => round($available / 1024),
            'used_mb' => round(($total - $available) / 1024),
            'used_percent' => round((($total - $available) / $total) * 100, 1),
        ];
    }

    private function disk(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! $total || ! $free) {
            return [];
        }

        return [
            'total_gb' => round($total / (1024 ** 3), 1),
            'free_gb' => round($free / (1024 ** 3), 1),
            'used_percent' => round((($total - $free) / $total) * 100, 1),
        ];
    }

    private function uptimeSeconds(): ?int
    {
        if (! is_readable('/proc/uptime')) {
            return null;
        }

        $raw = (float) strtok((string) file_get_contents('/proc/uptime'), ' ');

        return (int) $raw;
    }
}
