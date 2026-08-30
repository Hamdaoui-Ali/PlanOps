<?php

namespace App\Http\Controllers;

use App\Domain\Export\Queries\ExportQueryService;
use App\Http\Requests\ExportRequest;
use Symfony\Component\HttpFoundation\Response;

final class ExportController extends Controller
{
    public function projects(ExportQueryService $exports): Response
    {
        return response()->streamDownload(function () use ($exports): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['key', 'name', 'status', 'start_on', 'target_on', 'progress']);
            foreach ($exports->projects(request()->user()) as $project) {
                $eligible = (int) $project->eligible_task_count;
                fputcsv($handle, [$project->key, $project->name, $project->status->value, $project->start_on?->toDateString(), $project->target_on?->toDateString(), $eligible > 0 ? round(((int) $project->completed_task_count / $eligible) * 100, 2) : 0]);
            }
            fclose($handle);
        }, 'planops-projects.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function tasks(ExportQueryService $exports): Response
    {
        return response()->streamDownload(function () use ($exports): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['key', 'project', 'parent', 'title', 'status', 'priority', 'due_on', 'labels', 'created_at', 'updated_at']);
            foreach ($exports->tasks(request()->user()) as $task) {
                fputcsv($handle, [$task->project->key.'-'.$task->number, $task->project->name, $task->parent ? $task->project->key.'-'.$task->parent->number : null, $task->title, $task->status->value, $task->priority->value, $task->due_on?->toDateString(), $task->labels->pluck('name')->implode('|'), $task->created_at?->toIso8601String(), $task->updated_at?->toIso8601String()]);
            }
            fclose($handle);
        }, 'planops-tasks.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function activity(ExportRequest $request, ExportQueryService $exports): Response
    {
        if ($request->validated('format', 'csv') === 'json') {
            return response()->stream(function () use ($exports): void {
                echo '[';
                $first = true;
                foreach ($exports->activity(request()->user()) as $activity) {
                    if (! $first) echo ',';
                    $first = false;
                    echo json_encode($this->activityRow($activity), JSON_THROW_ON_ERROR);
                }
                echo ']';
            }, 200, ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename=planops-activity.json']);
        }

        return response()->streamDownload(function () use ($exports): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['created_at', 'project', 'task_key', 'event_type', 'field', 'old_value', 'new_value']);
            foreach ($exports->activity(request()->user()) as $activity) {
                $row = $this->activityRow($activity);
                fputcsv($handle, [$row['created_at'], $row['project'], $row['task_key'], $row['event_type'], $row['field'], json_encode($row['old_value']), json_encode($row['new_value'])]);
            }
            fclose($handle);
        }, 'planops-activity.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function activityRow(object $activity): array
    {
        return ['created_at' => $activity->created_at?->toIso8601String(), 'project' => $activity->project?->name, 'task_key' => $activity->task && $activity->project ? $activity->project->key.'-'.$activity->task->number : null, 'event_type' => $activity->event_type->value, 'field' => $activity->field, 'old_value' => $activity->old_value, 'new_value' => $activity->new_value];
    }
}
