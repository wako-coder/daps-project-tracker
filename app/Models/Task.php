<?php

namespace App\Models;

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
    protected $fillable = ['title', 'estimated_cost_santim', 'status'];

    protected $attributes = ['status' => 'todo'];

    protected function casts(): array
    {
        return [
            'estimated_cost_santim' => 'integer',
            'status' => TaskStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
