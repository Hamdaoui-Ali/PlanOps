<?php

namespace App\View\Components;

use App\Domain\Notifications\Models\PlanOpsNotification;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public int $unreadNotificationCount;

    public function __construct()
    {
        $this->unreadNotificationCount = auth()->id() === null
            ? 0
            : PlanOpsNotification::query()->forRecipient(auth()->id())->whereNull('read_at')->count();
    }

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
