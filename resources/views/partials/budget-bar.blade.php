{{-- Spent (done tasks) as a share of the project's budget. --}}
<div class="bar {{ $project->isOverBudget() ? 'bar-over' : '' }}"
     role="img"
     aria-label="{{ $project->spentPercent() }}% of budget spent">
    <span style="width: {{ $project->spentPercent() }}%"></span>
</div>
<small class="muted">
    {{ $project->spentPercent() }}% spent
    @if ($project->isOverBudget())
        · <span class="danger">planned work exceeds budget</span>
    @endif
</small>
