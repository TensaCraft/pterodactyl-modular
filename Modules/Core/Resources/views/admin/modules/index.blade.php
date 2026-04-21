@extends('layouts.admin')
@include('core-module::partials.admin.modules.nav', ['activeTab' => 'modules'])

@section('title')
    Modules
@endsection

@section('content-header')
    <h1>Modules<small>Manage installed modular packages.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Modules</li>
    </ol>
@endsection

@section('content')
    @yield('modules::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Installed Modules</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Capabilities</th>
                                <th>Activity</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modules as $module)
                                <tr>
                                    <td class="middle">
                                        <strong>{{ $module->name }}</strong>
                                        <div class="text-muted small">{{ $module->slug }}</div>
                                    </td>
                                    <td class="middle">{{ $module->version }}</td>
                                    <td class="middle">
                                        <span class="{{ $module->stateClass }}">{{ $module->stateText }}</span>
                                    </td>
                                    <td class="middle">
                                        @forelse ($module->capabilities as $capability)
                                            <span class="{{ $capability['class'] }}">{{ $capability['text'] }}</span>
                                        @empty
                                            <span class="text-muted small">None</span>
                                        @endforelse
                                    </td>
                                    <td
                                        data-module-operation-status
                                        data-module-slug="{{ $module->slug }}"
                                        data-status-url="{{ route('admin.modules.status', ['module' => $module->slug]) }}"
                                    >
                                        @if ($module->latestOperation)
                                            <span class="{{ $module->latestOperation->statusClass }}">{{ $module->latestOperation->statusText }}</span>
                                            <div class="text-muted small">
                                                {{ $module->latestOperation->actionText }}
                                                @if ($module->latestOperation->progressText !== 'n/a')
                                                    · {{ $module->latestOperation->progressText }}
                                                @endif
                                            </div>
                                            @if ($module->latestOperation->detailText)
                                                <div class="small {{ $module->latestOperation->error ? 'text-danger' : 'text-muted' }}">
                                                    {{ $module->latestOperation->detailText }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="label label-default">Idle</span>
                                            <div class="text-muted small">No operations recorded.</div>
                                        @endif
                                    </td>
                                    <td class="text-right middle">
                                        <div class="btn-group btn-group-xs" role="group" aria-label="Module actions">
                                        @if ($module->canInstall)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'install']) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-primary"><i class="fa fa-download"></i> Install</button>
                                            </form>
                                        @endif
                                        @if ($module->canEnable)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'enable']) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-success"><i class="fa fa-play"></i> Enable</button>
                                            </form>
                                        @endif
                                        @if ($module->canUpdate)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'update']) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-info"><i class="fa fa-refresh"></i> Update</button>
                                            </form>
                                        @endif
                                        @if ($module->canDisable)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'disable']) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-warning"><i class="fa fa-pause"></i> Disable</button>
                                            </form>
                                        @endif
                                        @if ($module->canDelete)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'delete']) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete module [{{ $module->name }}]? This removes the module files from disk.');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Delete</button>
                                            </form>
                                        @endif
                                        @if ($module->canRebuild)
                                            <form action="{{ route('admin.modules.action', ['module' => $module->slug, 'action' => 'rebuild-registry']) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-default"><i class="fa fa-cubes"></i> Rebuild</button>
                                            </form>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(function () {
            var statusCells = $('[data-module-operation-status]');

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function statusLabelClass(status) {
                switch (status) {
                    case 'succeeded':
                        return 'label label-success';
                    case 'running':
                        return 'label label-info';
                    case 'queued':
                        return 'label label-warning';
                    case 'failed':
                        return 'label label-danger';
                    default:
                        return 'label label-default';
                }
            }

            function titleize(value) {
                return (value || '')
                    .replace(/-/g, ' ')
                    .replace(/\b\w/g, function (character) {
                        return character.toUpperCase();
                    });
            }

            function renderModuleOperation(operation) {
                if (!operation) {
                    return '<span class="label label-default">Idle</span><div class="text-muted small">No operations recorded.</div>';
                }

                var detail = operation.error;

                if (!detail && Array.isArray(operation.steps) && operation.steps.length > 0) {
                    var activeStep = operation.steps.find(function (step) {
                        return step.status !== 'completed';
                    }) || operation.steps[operation.steps.length - 1];

                    detail = activeStep && activeStep.label ? activeStep.label : '';
                }

                return [
                    '<span class="' + statusLabelClass(operation.status) + '">' + escapeHtml(titleize(operation.status)) + '</span>',
                    '<div class="text-muted small">' + escapeHtml(titleize(operation.action)) + (typeof operation.progress === 'number' ? ' · ' + escapeHtml(operation.progress + '%') : '') + '</div>',
                    detail ? '<div class="small ' + (operation.error ? 'text-danger' : 'text-muted') + '">' + escapeHtml(detail) + '</div>' : ''
                ].join('');
            }

            function refreshOperation(cell) {
                $.getJSON(cell.data('status-url'))
                    .done(function (payload) {
                        cell.html(renderModuleOperation(payload.data.operation));
                    })
                    .fail(function () {
                        cell.html('<span class="label label-danger">Error</span><div class="text-danger small">Unable to load operation status.</div>');
                    });
            }

            statusCells.each(function () {
                var cell = $(this);
                refreshOperation(cell);
                window.setInterval(function () {
                    refreshOperation(cell);
                }, 5000);
            });
        });
    </script>
@endsection
