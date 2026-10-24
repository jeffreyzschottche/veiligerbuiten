<?php

namespace App\Support;

/**
 * Bescherming tegen SSRF: de server stuurt webhooks naar adressen die gebruikers
 * zelf invullen. Die mogen nooit naar het eigen netwerk wijzen (localhost,
 * 10.x, 192.168.x, cloud-metadata op 169.254.169.254, enz.).
 *
 * resolve() geeft het gecontroleerde publieke IP terug; de aanroeper pint dat
 * vast (CURLOPT_RESOLVE), zodat een DNS-naam niet tussen controle en verzoek
 * alsnog naar een intern adres kan wisselen (DNS-rebinding).
 */
class SafeUrl
{
    /** Alleen voor tests: hostnaam => IP's, zodat er geen echte DNS nodig is. */
    private static array $fakeDns = [];

    public static function fakeDns(array $map): void
    {
        self::$fakeDns = $map;
    }

    /** @return array{host: string, port: int, ip: string}|null null = niet toegestaan */
    public static function resolve(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = strtolower(trim($parts['host'], '[]'));
        $port = (int) ($parts['port'] ?? 443);
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::lookup($host);
        if ($ips === [] || array_filter($ips, fn (string $ip) => ! self::isPublic($ip)) !== []) {
            return null;
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public static function isPublic(string $ip): bool
    {
        // ::ffff:10.0.0.1 en dergelijke: beoordeel het IPv4-deel.
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $mapped)) {
            $ip = $mapped[1];
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        // Carrier-grade NAT (100.64.0.0/10) en IPv6 link-local/unique-local vallen niet onder de filters hierboven.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return ! preg_match('/^(fe80|fc|fd)/i', $ip);
    }

    /** @return list<string> */
    private static function lookup(string $host): array
    {
        if (array_key_exists($host, self::$fakeDns)) {
            return (array) self::$fakeDns[$host];
        }
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
