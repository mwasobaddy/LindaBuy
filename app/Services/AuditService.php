<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?User $user = null,
        array $details = [],
        ?Request $request = null,
    ): AuditLog {
        try {
            $user ??= auth()->user();
            $request ??= request();

            return AuditLog::create([
                'user_id' => $user?->id,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'details' => $details,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create audit log entry', [
                'action' => $action,
                'entity' => $entity,
                'error' => $e->getMessage(),
            ]);

            return new AuditLog;
        }
    }

    public function query(array $filters = [], int $perPage = 50): mixed
    {
        $query = AuditLog::with('user');

        $query->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v));
        $query->when($filters['entity'] ?? null, fn ($q, $v) => $q->where('entity', $v));
        $query->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v));
        $query->when($filters['entity_id'] ?? null, fn ($q, $v) => $q->where('entity_id', $v));
        $query->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v));
        $query->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));

        return $query->orderBy('created_at', 'desc')->paginate(min($perPage, 100));
    }

    public function summary(?string $entity = null, int $days = 30): mixed
    {
        $query = AuditLog::select('action', DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays($days));

        if ($entity !== null) {
            $query->where('entity', $entity);
        }

        return $query->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();
    }
}
