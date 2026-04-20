@extends('layouts.admin')
@include('core-module::partials.admin.modules.nav', ['activeTab' => 'logs'])

@section('title')
    Module Logs
@endsection

@section('content-header')
    <h1>Module Logs<small>Review recent module operations.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.modules.index') }}">Modules</a></li>
        <li class="active">Logs</li>
    </ol>
@endsection

@section('content')
    @yield('modules::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-info">
                No recent module operations are available once the log is cleared.
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Operations</h3>
                    <div class="box-tools">
                        <form action="{{ route('admin.modules.operations.clear') }}" method="POST" style="display:inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Clear Logs</button>
                        </form>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Target</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody data-module-operations-feed data-operations-url="{{ route('admin.modules.operations.index') }}">
                            @forelse ($operations as $operation)
                                <tr>
                                    <td class="middle">
                                        <strong>{{ $operation->targetText }}</strong>
                                        <div class="text-muted small">#{{ $operation->id }}</div>
                                    </td>
                                    <td class="middle">{{ $operation->actionText }}</td>
                                    <td class="middle"><span class="{{ $operation->statusClass }}">{{ $operation->statusText }}</span></td>
                                    <td class="middle">{{ $operation->progressText }}</td>
                                    <td class="middle">
                                        @if ($operation->detailText)
                                            <span class="{{ $operation->error ? 'text-danger' : 'text-muted' }}">{{ $operation->detailText }}</span>
                                        @else
                                            <span class="text-muted">No detail recorded.</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">No operations recorded.</td>
                                </tr>
                            @endforelse
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
            var operationsFeed = $('[data-module-operations-feed]');

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

            function renderOperationRow(operation) {
                var target = operation.module_slug || ((operation.source && operation.source.type) ? titleize(operation.source.type) + ' Import' : 'Unknown');
                var detail = operation.error || (operation.source ? (operation.source.repository || operation.source.path || '') : '');

                return [
                    '<tr>',
                    '<td class="middle"><strong>' + escapeHtml(target) + '</strong><div class="text-muted small">#' + escapeHtml(operation.id) + '</div></td>',
                    '<td class="middle">' + escapeHtml(titleize(operation.action)) + '</td>',
                    '<td class="middle"><span class="' + statusLabelClass(operation.status) + '">' + escapeHtml(titleize(operation.status)) + '</span></td>',
                    '<td class="middle">' + escapeHtml(typeof operation.progress === 'number' ? operation.progress + '%' : 'n/a') + '</td>',
                    '<td class="middle">' + (detail ? '<span class="' + (operation.error ? 'text-danger' : 'text-muted') + '">' + escapeHtml(detail) + '</span>' : '<span class="text-muted">No detail recorded.</span>') + '</td>',
                    '</tr>'
                ].join('');
            }

            function refreshOperationsFeed(feed) {
                $.getJSON(feed.data('operations-url'))
                    .done(function (payload) {
                        var rows = payload.data || [];

                        if (rows.length === 0) {
                            feed.html('<tr><td colspan="5" class="text-muted">No operations recorded.</td></tr>');
                            return;
                        }

                        feed.html(rows.map(renderOperationRow).join(''));
                    })
                    .fail(function () {
                        feed.html('<tr><td colspan="5" class="text-danger">Unable to load recent operations.</td></tr>');
                    });
            }

            operationsFeed.each(function () {
                var feed = $(this);
                refreshOperationsFeed(feed);
                window.setInterval(function () {
                    refreshOperationsFeed(feed);
                }, 5000);
            });
        });
    </script>
@endsection
