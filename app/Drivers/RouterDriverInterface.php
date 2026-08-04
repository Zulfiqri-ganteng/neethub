<?php

namespace App\Drivers;

/**
 * Strategy pattern contract every router brand driver must implement
 * (MikroTik first; Ubiquiti/Omada/OpenWRT/Huawei/Cisco/Ruijie later —
 * structure is ready, see RouterDriverFactory).
 */
interface RouterDriverInterface
{
    public function testConnection(): bool;

    public function addUser(string $username, string $password, int $downloadMbps, int $uploadMbps): void;

    public function updateUser(string $username, ?string $password, ?int $downloadMbps, ?int $uploadMbps): void;

    public function removeUser(string $username): void;

    public function suspendUser(string $username): void;

    public function enableUser(string $username): void;

    /**
     * @return array<int, array<string, string>>
     */
    public function getOnlineUsers(): array;

    /**
     * @return array<string, string>
     */
    public function getTraffic(string $username): array;

    public function reboot(): void;
}
