<?php

namespace App\Http\Middleware;

use App\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMpesaIp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }

        $allowedIps = [
            '196.201.214.200',
            '196.201.214.201',
            '196.201.214.202',
            '196.201.214.203',
            '10.0.0.0/8',
        ];

        $ip = $request->ip();

        if (! $this->ipInRange($ip, $allowedIps)) {
            return ApiResponse::forbidden('Unauthorized IP address.');
        }

        return $next($request);
    }

    protected function ipInRange(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (str_contains($range, '/')) {
                [$subnet, $bits] = explode('/', $range, 2);
                $ipLong = ip2long($ip);
                $subnetLong = ip2long($subnet);
                $mask = -1 << (32 - (int) $bits);

                if ($ipLong === false || $subnetLong === false) {
                    continue;
                }

                if (($ipLong & $mask) === ($subnetLong & $mask)) {
                    return true;
                }
            } elseif ($ip === $range) {
                return true;
            }
        }

        return false;
    }
}
