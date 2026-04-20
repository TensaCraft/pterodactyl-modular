@extends('layouts.admin')
@include('core-module::partials.admin.modules.nav', ['activeTab' => 'installation'])

@section('title')
    Module Installation
@endsection

@section('content-header')
    <h1>Module Installation<small>Import and install modular packages.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.modules.index') }}">Modules</a></li>
        <li class="active">Installation</li>
    </ol>
@endsection

@section('content')
    @yield('modules::nav')
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-info">
                Install or update modules from an archive package or a Git repository.
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Archive Package</h3>
                </div>
                <form action="{{ route('admin.modules.import.archive') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-8">
                                <label class="control-label" for="pArchive">Archive <span class="field-required"></span></label>
                                <div>
                                    <input id="pArchive" type="file" name="archive" accept=".zip,application/zip" class="form-control" required />
                                    <p class="small text-muted">Upload a ZIP package that contains exactly one module directory with a <code>module.json</code> manifest.</p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Options</label>
                                <div>
                                    <div class="checkbox checkbox-primary">
                                        <input id="pInstallArchiveModule" type="checkbox" name="install_after_import" value="1" checked />
                                        <label for="pInstallArchiveModule">Run install lifecycle after import</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input id="pEnableArchiveModule" type="checkbox" name="enable_after_import" value="1" />
                                        <label for="pEnableArchiveModule">Enable module after install</label>
                                    </div>
                                    <div class="checkbox checkbox-primary no-margin-bottom">
                                        <input id="pReplaceArchiveModule" type="checkbox" name="replace_existing" value="1" />
                                        <label for="pReplaceArchiveModule">Replace existing module if slug matches</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">Queue Archive Import</button>
                    </div>
                </form>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Git Repository</h3>
                </div>
                <form action="{{ route('admin.modules.import.git') }}" method="POST">
                    @csrf
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label" for="pRepository">Repository URL <span class="field-required"></span></label>
                                <div>
                                    <input id="pRepository" type="text" name="repository" class="form-control" placeholder="https://example.com/module.git" required />
                                    <p class="small text-muted">Clone a repository that contains exactly one module package at its root.</p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label" for="pReference">Reference</label>
                                <div>
                                    <input id="pReference" type="text" name="reference" class="form-control" placeholder="main, tag, or commit (optional)" />
                                    <p class="small text-muted">Leave empty to use the repository default branch.</p>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Options</label>
                                <div>
                                    <div class="checkbox checkbox-primary">
                                        <input id="pInstallGitModule" type="checkbox" name="install_after_import" value="1" checked />
                                        <label for="pInstallGitModule">Run install lifecycle after import</label>
                                    </div>
                                    <div class="checkbox checkbox-primary">
                                        <input id="pEnableGitModule" type="checkbox" name="enable_after_import" value="1" />
                                        <label for="pEnableGitModule">Enable module after install</label>
                                    </div>
                                    <div class="checkbox checkbox-primary no-margin-bottom">
                                        <input id="pReplaceGitModule" type="checkbox" name="replace_existing" value="1" />
                                        <label for="pReplaceGitModule">Replace existing module if slug matches</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-sm btn-primary pull-right">Queue Git Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
