<?php

namespace Pterodactyl\Console\Commands\Node;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Allocation;
use Illuminate\Console\Command;
use Pterodactyl\Services\Nodes\NodeCreationService;

class AutoConfigureNodeCommand extends Command
{
    protected $signature = 'p:node:auto {--force : Re-sync configuration even if node already exists}';

    protected $description = 'Automatically detect host system resources (RAM, Disk, CPU) and configure a live local node and port allocations.';

    public function __construct(private NodeCreationService $creationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->output->title('N1X Auto Node Configuration (Host Resource Auto-Detection)');

        // 1. Detect Host Resources
        $ramMb = $this->detectRamMb();
        $diskMb = $this->detectDiskMb();
        $hostname = gethostname() ?: 'n1x-server';

        $this->line(sprintf('Detected System RAM : <fg=green>%d MiB (%.1f GB)</>', $ramMb, $ramMb / 1024));
        $this->line(sprintf('Detected System Disk: <fg=green>%d MiB (%.1f GB)</>', $diskMb, $diskMb / 1024));
        $this->line(sprintf('Host Machine Name   : <fg=green>%s</>', $hostname));

        // 2. Location
        $location = Location::query()->firstOrCreate(
            ['short' => 'local'],
            ['long' => 'N1X Host Server (' . $hostname . ')']
        );
        $this->info("Location: {$location->short} (ID: {$location->id})");

        // 3. Node
        $node = Node::query()->where('fqdn', '127.0.0.1')
            ->orWhere('name', 'like', 'N1X Host%')
            ->first();

        if (!$node) {
            $this->comment('Creating new Local Node with detected server resources...');
            $node = $this->creationService->handle([
                'name' => 'N1X Host - ' . $hostname,
                'description' => 'Auto-configured Native Application Node with server resources: ' . round($ramMb / 1024, 1) . ' GB RAM, ' . round($diskMb / 1024, 1) . ' GB Disk',
                'location_id' => $location->id,
                'public' => true,
                'fqdn' => '127.0.0.1',
                'scheme' => 'http',
                'behind_proxy' => false,
                'memory' => $ramMb,
                'memory_overallocate' => 0,
                'disk' => $diskMb,
                'disk_overallocate' => 0,
                'upload_size' => 1024,
                'daemonBase' => '/var/lib/pterodactyl/volumes',
                'daemonSFTP' => 2022,
                'daemonListen' => 8080,
                'maintenance_mode' => false,
            ]);
            $this->info("Node created successfully! ID: {$node->id}, UUID: {$node->uuid}");
        } else {
            $this->comment("Updating existing Node (ID: {$node->id}) with latest host resources...");
            $node->update([
                'memory' => $ramMb,
                'disk' => $diskMb,
                'daemonListen' => 8080,
                'scheme' => 'http',
                'maintenance_mode' => false,
                'description' => 'Auto-configured Native Application Node with server resources: ' . round($ramMb / 1024, 1) . ' GB RAM, ' . round($diskMb / 1024, 1) . ' GB Disk',
            ]);
            $this->info("Node ID: {$node->id} updated successfully!");
        }

        // 4. Ensure Port Allocations for Node (Ports 5001-5050 on 127.0.0.1)
        $existingPorts = Allocation::query()->where('node_id', $node->id)->pluck('port')->toArray();
        $createdCount = 0;
        for ($port = 5001; $port <= 5050; $port++) {
            if (!in_array($port, $existingPorts)) {
                Allocation::query()->create([
                    'node_id' => $node->id,
                    'ip' => '127.0.0.1',
                    'port' => $port,
                    'ip_alias' => null,
                    'server_id' => null,
                    'notes' => 'Auto-allocated for web applications',
                ]);
                $createdCount++;
            }
        }
        $totalAllocations = Allocation::query()->where('node_id', $node->id)->count();
        $this->info("Allocations: {$totalAllocations} total ports available on 127.0.0.1 ({$createdCount} new ports added).");

        // 5. Synchronize token and UUID with n1x-runner
        $runnerConfigPath = base_path('n1x-runner/config.json');
        if (file_exists($runnerConfigPath)) {
            $runnerConfig = json_decode(file_get_contents($runnerConfigPath), true) ?: [];
            $runnerConfig['token'] = $node->getDecryptedKey();
            $runnerConfig['token_id'] = $node->daemon_token_id;
            $runnerConfig['uuid'] = $node->uuid;
            $runnerConfig['node_id'] = $node->id;
            $runnerConfig['panel_url'] = config('app.url', 'http://127.0.0.1:5000');
            $runnerConfig['host_ram_mb'] = $ramMb;
            $runnerConfig['host_disk_mb'] = $diskMb;
            file_put_contents($runnerConfigPath, json_encode($runnerConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Synchronized runner configuration with n1x-runner/config.json');
        }

        $this->output->success('Auto Node Setup Complete! Node is LIVE and ready to deploy applications.');
        return 0;
    }

    protected function detectRamMb(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            @exec('powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize"', $output, $status);
            if ($status === 0 && !empty($output)) {
                $kb = (int) trim(implode('', $output));
                if ($kb > 0) {
                    return (int) round($kb / 1024);
                }
            }

            @exec('wmic OS get TotalVisibleMemorySize /Value', $wmicOut);
            foreach ($wmicOut as $line) {
                if (str_starts_with($line, 'TotalVisibleMemorySize=')) {
                    $kb = (int) trim(substr($line, 23));
                    if ($kb > 0) {
                        return (int) round($kb / 1024);
                    }
                }
            }
        } else {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo && preg_match('/MemTotal:\s+(\d+)\s+kB/i', $meminfo, $matches)) {
                return (int) round(((int) $matches[1]) / 1024);
            }
        }

        return 8192;
    }

    protected function detectDiskMb(): int
    {
        $path = base_path();
        $total = @disk_total_space($path);
        if ($total && $total > 0) {
            return (int) round($total / 1024 / 1024);
        }

        return 102400;
    }
}
