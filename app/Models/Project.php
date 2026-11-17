<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    // organization_id is deliberately not fillable: a project's tenant is set
    // only by creating it through $organization->projects().
    protected $fillable = ['name', 'description', 'budget_santim'];

    protected function casts(): array
    {
        return ['budget_santim' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Adds two totals, computed in SQL:
     *  - spent_santim:     estimated cost of tasks that are done
     *  - committed_santim: estimated cost of every task
     */
    public function scopeWithCostTotals(Builder $query): void
    {
        $query
            ->withSum(['tasks as spent_santim' => fn (Builder $tasks) => $tasks->where('status', TaskStatus::Done->value)], 'estimated_cost_santim')
            ->withSum('tasks as committed_santim', 'estimated_cost_santim');
    }

    public function spent(): int
    {
        return (int) $this->spent_santim;
    }

    public function committed(): int
    {
        return (int) $this->committed_santim;
    }

    /** Share of the budget already spent, capped at 100 for the progress bar. */
    public function spentPercent(): int
    {
        if ($this->budget_santim <= 0) {
            return $this->spent() > 0 ? 100 : 0;
        }

        return (int) min(100, intdiv($this->spent() * 100, $this->budget_santim));
    }

    public function isOverBudget(): bool
    {
        return $this->committed() > $this->budget_santim;
    }
}
