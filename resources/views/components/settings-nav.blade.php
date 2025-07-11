@props(['active' => 'overview'])

<div class="card">

    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="{{ route('settings.index') }}" 
               class="list-group-item list-group-item-action {{ $active === 'overview' ? 'active' : '' }}">
                <i class="bi bi-house me-2"></i>Overview
            </a>
            <a href="{{ route('settings.account') }}" 
               class="list-group-item list-group-item-action {{ $active === 'account' ? 'active' : '' }}">
                <i class="bi bi-person me-2"></i>Account
            </a>
            <a href="{{ route('settings.notifications') }}" 
               class="list-group-item list-group-item-action {{ $active === 'notifications' ? 'active' : '' }}">
                <i class="bi bi-bell me-2"></i>Notifications
            </a>
            <a href="{{ route('settings.spans') }}" 
               class="list-group-item list-group-item-action {{ $active === 'spans' ? 'active' : '' }}">
                <i class="bi bi-diagram-3 me-2"></i>Privacy
            </a>
            <a href="{{ route('settings.groups') }}" 
               class="list-group-item list-group-item-action {{ $active === 'groups' ? 'active' : '' }}">
                <i class="bi bi-people me-2"></i>Groups
            </a>
            <a href="{{ route('settings.import') }}" 
               class="list-group-item list-group-item-action {{ $active === 'import' ? 'active' : '' }}">
                <i class="bi bi-download me-2"></i>Import
            </a>
            <a href="#" 
               class="list-group-item list-group-item-action {{ $active === 'export' ? 'active' : '' }}">
                <i class="bi bi-upload me-2"></i>Export
            </a>
        </div>
    </div>
</div> 