<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    // project_id is deliberately not fillable: a task's project is set only by
    // creating it through $project->tasks().
    protected $fillable = ['title', 'estimated_cost_santim', 'status', 'priority'];

    protected $attributes = ['status' => 'todo', 'priority' => 'medium'];

    protected function casts(): array
    {
        return [
            'estimated_cost_santim' => 'integer',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
