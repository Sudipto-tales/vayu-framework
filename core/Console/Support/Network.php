<?php

namespace Vayu\Console\Support;

/**
 * Small helpers for discovering the machine's LAN address so the dev
 * server can advertise a Network URL reachable from other devices.
 */
final class Network
{
    /**
     * Best-effort detection of the primary private IPv4 address.
     */
    public static function localIp(): ?string
    {
        // 1. `hostname -I` lists all bound addresses (Linux).
        $output = @shell_exec('hostname -I 2>/dev/null');
        if (is_string($output)) {
            foreach (preg_split('/\s+/', trim($output)) ?: [] as $candidate) {
                if (self::isPrivateIpv4($candidate)) {
                    return $candidate;
                }
            }
        }

        // 2. Ask the OS which local address it would use to reach the internet.
        //    A UDP "connect" assigns a source address without sending traffic.
        $sock = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
        if ($sock !== false) {
            $name = @stream_socket_get_name($sock, false);
            fclose($sock);
            if (is_string($name) && $name !== '') {
                $ip = explode(':', $name)[0];
                if (self::isPrivateIpv4($ip)) {
                    return $ip;
                }
            }
        }

        // 3. Fall back to resolving the hostname.
        $ip = gethostbyname(php_uname('n'));
        if (self::isPrivateIpv4($ip)) {
            return $ip;
        }

        return null;
    }

    public static function isPrivateIpv4(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $parts = array_map('intval', explode('.', $ip));
        [$a, $b] = [$parts[0], $parts[1]];

        // RFC 1918 private ranges (what a home/office LAN uses).
        return $a === 10
            || ($a === 192 && $b === 168)
            || ($a === 172 && $b >= 16 && $b <= 31);
    }
}
