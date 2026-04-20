@section('modules::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="nav-tabs-custom nav-tabs-floating">
                <ul class="nav nav-tabs">
                    <li @if($activeTab === 'modules')class="active"@endif><a href="{{ route('admin.modules.index') }}">Modules</a></li>
                    <li @if($activeTab === 'logs')class="active"@endif><a href="{{ route('admin.modules.logs') }}">Logs</a></li>
                    <li @if($activeTab === 'installation')class="active"@endif><a href="{{ route('admin.modules.installation') }}">Installation</a></li>
                </ul>
            </div>
        </div>
    </div>
@endsection
