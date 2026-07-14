@extends('layouts.master')
@section('title','MiladMk - '.__('setting-autobackup-menu'))
@section('content')
    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">{{__('setting-autobackup-menu')}}</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        @include('layouts.setting_menu')
                        <div class="tab-content">
                            <div class="card-body">

                                @if(session('alert'))
                                    <div class="alert alert-info">{{ session('alert') }}</div>
                                @endif

                                <div class="alert alert-warning">{{__('setting-autobackup-desc')}}</div>

                                <form action="{{ route('settings.autobackup') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>{{__('setting-autobackup-apitoken')}}</label>
                                                <input type="text" name="api_token" class="form-control" value="{{ $ab->api_token ?? '' }}" placeholder="Panel API Token" required>
                                                <small>{{__('setting-autobackup-apitoken-desc')}}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-autobackup-token')}}</label>
                                                <input type="text" name="bot_token" class="form-control" value="{{ $ab->bot_token ?? '' }}" placeholder="123456:ABC-..." required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-autobackup-chatid')}}</label>
                                                <input type="text" name="chat_id" class="form-control" value="{{ $ab->chat_id ?? '' }}" placeholder="123456789" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-autobackup-name')}}</label>
                                                <input type="text" name="backup_name" class="form-control" value="{{ $ab->backup_name ?? 'backup' }}" placeholder="myserver" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>{{__('setting-autobackup-time')}}</label>
                                                <input type="time" name="run_time" class="form-control" value="{{ $ab->run_time ?? '02:00' }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check form-switch p-0 mt-4">
                                                <input class="form-check-input h4 position-relative m-0" type="checkbox" role="switch" name="status" id="status" value="active" {{ (($ab->status ?? '')=='active')?'checked':'' }}>
                                                <label class="form-check-label ms-2" for="status">{{__('setting-autobackup-active')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-2">{{__('setting-save')}}</button>
                                </form>

                                <hr>

                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <a href="{{ route('settings.autobackup.run') }}" class="btn btn-success"
                                       onclick="return confirm('Run backup now and send to Telegram?')">
                                        <i class="ti ti-cloud-upload"></i> {{__('setting-autobackup-run')}}
                                    </a>
                                    @if($ab && $ab->last_run_at)
                                        <span class="text-muted">{{__('setting-autobackup-lastrun')}}: {{ $ab->last_run_at }}</span>
                                    @endif
                                </div>

                                <label>{{__('setting-autobackup-log')}}</label>
                                <pre style="background:#0b1020;color:#a8e6a1;padding:15px;border-radius:8px;min-height:120px;max-height:300px;overflow:auto;white-space:pre-wrap;">{{ $ab->last_log ?? '—' }}</pre>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
