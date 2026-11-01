<?php

namespace Pterodactyl\Services\N1x;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Exceptions\DisplayException;

class PortManagerService
{
    protected int $rangeStart;
    protected int $rangeEnd;

    public function __construct()
    {
        $this->rangeStart = (int) config('n1x.applications.port_range_start', 5000);
        $this->rangeEnd = (int) config('n1x.applications.port_range_end', 9000);
    }

    /**
     * Find an available internal port on the node within the configured range.
     *
     * @throws DisplayException
     */
    public function allocatePort(Node $node, ?int $requestedPort = null): int
    {
        if ($requestedPort !== null) {
            if (!$this->isPortAvailable($node, $requestedPort)) {
                throw new DisplayException("Port {$requestedPort} is already in use or unavailable on node {$node->name}.");
            }
            return $requestedPort;
        }

        // Fetch all assigned ports on this node within the range
        $usedPorts = Allocation::query()
            ->where('node_id', $node->id)
            ->whereBetween('port', [$this->rangeStart, $this->rangeEnd])
            ->pluck('port')
            ->toArray();

        for ($port = $this->rangeStart; $port <= $this->rangeEnd; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        throw new DisplayException("No available ports in range {$this->rangeStart}-{$this->rangeEnd} on node {$node->name}.");
    }

    /**
     * Check if a specific port is available on the specified node.
     */
    public function isPortAvailable(Node $node, int $port): bool
    {
        if ($port < 1024 || $port > 65535) {
            return false;
        }

        $exists = Allocation::query()
            ->where('node_id', $node->id)
            ->where('port', $port)
            ->whereNotNull('server_id')
            ->exists();

        return !$exists;
    }

    /**
     * Get or create an Allocation model for the assigned port on the node.
     */
    public function getOrCreateAllocation(Node $node, int $port, string $ip = '0.0.0.0'): Allocation
    {
        return Allocation::query()->firstOrCreate(
            [
                'node_id' => $node->id,
                'ip' => $ip,
                'port' => $port,
            ],
            [
                'ip_alias' => null,
            ]
        );
    }
}

