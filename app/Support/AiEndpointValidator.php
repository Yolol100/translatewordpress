<?php

declare(strict_types=1);

namespace Webactueel\Translate\Support;

if (! defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reviewed: public wat_* hooks are intentional.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Temporary error handlers are used to safely inspect DNS records.

final class AiEndpointValidator
{
    public static function sanitize($value): string
    {
        $endpoint = esc_url_raw(Input::scalar_string($value));
        if ($endpoint === '') {
            return '';
        }

        $parts = wp_parse_url($endpoint);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            return '';
        }

        if (! empty($parts['user']) || ! empty($parts['pass'])) {
            return '';
        }

        if (! empty($parts['query']) || ! empty($parts['fragment'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        $allowedHosts = apply_filters('wat_ai_custom_endpoint_allowed_hosts', []);
        $requiresAllowList = (bool) apply_filters('wat_ai_require_custom_endpoint_allowlist', true, $endpoint, $host);
        if ($requiresAllowList && (! is_array($allowedHosts) || $allowedHosts === [])) {
            return '';
        }
        if (is_array($allowedHosts) && $allowedHosts !== []) {
            $normalizedHosts = [];
            foreach ($allowedHosts as $allowedHost) {
                if (! is_scalar($allowedHost)) {
                    continue;
                }
                $allowedHost = strtolower(trim((string) $allowedHost));
                if ($allowedHost !== '') {
                    $normalizedHosts[] = $allowedHost;
                }
            }
            if (! in_array($host, array_values(array_unique($normalizedHosts)), true)) {
                return '';
            }
        }

        $port = absint($parts['port'] ?? 443);
        $allowedPorts = apply_filters('wat_ai_custom_endpoint_allowed_ports', [443]);
        if (! is_array($allowedPorts)) {
            return '';
        }
        $allowedPorts = array_values(array_unique(array_filter(array_map(
            static fn($allowedPort): int => is_scalar($allowedPort) ? absint($allowedPort) : 0,
            $allowedPorts
        ))));
        if (! in_array($port, $allowedPorts, true)) {
            return '';
        }

        $isPrivateIp = filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local');
        $allowPrivate = (bool) apply_filters('wat_ai_allow_private_custom_endpoint', false, $endpoint, $host);
        if (($isPrivateIp || $isLocalHost || self::host_resolves_to_private_address($host)) && ! $allowPrivate) {
            return '';
        }

        return $endpoint;
    }

    /**
     * Guard custom AI endpoints against DNS rebinding to local or reserved networks.
     */
    private static function host_resolves_to_private_address(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $addresses = [];
        if (function_exists('gethostbynamel')) {
            $ipv4 = gethostbynamel($host);
            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }
        }

        if (function_exists('dns_get_record')) {
            set_error_handler(static function (): bool {
                return true;
            });
            try {
                $records = dns_get_record($host, DNS_A + DNS_AAAA);
            } finally {
                restore_error_handler();
            }
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (! empty($record['ip']) && is_string($record['ip'])) {
                        $addresses[] = $record['ip'];
                    }
                    if (! empty($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        foreach (array_unique($addresses) as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP)
                && ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                return true;
            }
        }

        return false;
    }
}
