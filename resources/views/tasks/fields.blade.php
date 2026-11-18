{{-- Shared by the "add a task" form and the edit page. $task is null when adding. --}}
<label class="grow">
    Title
    <input type="text" name="title" value="{{ old('title', $task?->title) }}" required maxlength="255">
</label>

<label>
    Estimated cost (ETB)
    <input type="text" name="estimated_cost" inputmode="decimal" placeholder="1500.00" required
           value="{{ old('estimated_cost', $task ? \App\Support\Money::toInput($task->estimated_cost_santim) : '') }}">
</label>

<label>
    Status
    <select name="status">
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(old('status', $task?->status->value ?? 'todo') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
</label>

<label>
    Priority
    <select name="priority">
        @foreach ($priorities as $option)
            <option value="{{ $option->value }}" @selected(old('priority', $task?->priority->value ?? 'medium') === $option->value)>{{ $option->label() }}</option>
        @endforeach
    </select>
</label>
