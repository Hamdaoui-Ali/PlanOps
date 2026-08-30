@if ($task->attention_reasons)
    <div class="task-attention-indicator" role="note" aria-label="Attention suggestions for {{ $task->title }}">
        <span class="sr-only">Review this task:</span>
        <strong>Review this task</strong>
        <span>{{ implode(' · ', $task->attention_reasons) }}</span>
    </div>
@endif
