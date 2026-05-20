<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'kyc_status', 'approved_at', 'rejected_at', 'rejected_reason'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Get the user who is this agent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Orders assigned to this agent.
     */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'agent_id');
    }

    /**
     * Issue reports created by this agent.
     */
    public function issueReports(): HasMany
    {
        return $this->hasMany(OrderIssueReport::class);
    }
}
