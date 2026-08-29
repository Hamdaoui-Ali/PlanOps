@props(['selection'])
<form method="GET" action="{{ route('dashboard') }}" class="dashboard-period-selector" aria-label="Dashboard period">
    <div><label for="dashboard-period">Period</label><select id="dashboard-period" name="period">@foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'custom' => 'Custom'] as $value => $label)<option value="{{ $value }}" @selected(($selection['period'] ?? 'today') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div><label for="dashboard-from">From</label><input id="dashboard-from" type="date" name="from" value="{{ $selection['from'] ?? '' }}"></div>
    <div><label for="dashboard-until">Until</label><input id="dashboard-until" type="date" name="until" value="{{ $selection['until'] ?? '' }}"></div>
    <button type="submit" class="planops-button planops-button-primary">Apply period</button>
</form>
