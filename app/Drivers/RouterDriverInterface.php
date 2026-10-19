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

    /**
     * Creates the user, or — if one with this username already exists on
     * the router (e.g. migrating a customer whose secret was made by hand
     * before NetHub) — adopts it, overwriting password/speed/disabled to
     * match NetHub. Null speeds leave the router's current limit untouched.
     */
    public function upsertUser(string $username, string $password, ?int $downloadMbps, ?int $uploadMbps, bool $disabled): void;

    /**
     * No-op if the user doesn't exist on the router (already gone is the
     * outcome the caller wanted anyway).
     */
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
