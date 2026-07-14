@extends('layouts.master')
@section('title','MiladMk - '.__('setting-serverip-menu'))
@section('content')
    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">{{__('setting-serverip-menu')}}</h2>
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

                                <div class="alert alert-warning">{{__('setting-serverip-desc')}}</div>

                                <form action="{{ route('settings.serverip') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-provider')}}</label>
                                                <select name="provider" class="form-select">
                                                    <option value="hetzner" {{ ($cfg->provider ?? 'hetzner')=='hetzner'?'selected':'' }}>Hetzner</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-location')}}</label>
                                                <select name="location" class="form-select">
                                                    @php
                                                        $locs = [
                                                            'fsn1' => 'Falkenstein (fsn1) — Germany',
                                                            'nbg1' => 'Nuremberg (nbg1) — Germany',
                                                            'hel1' => 'Helsinki (hel1) — Finland',
                                                            'ash'  => 'Ashburn (ash) — USA East',
                                                            'hil'  => 'Hillsboro (hil) — USA West',
                                                            'sin'  => 'Singapore (sin) — Asia',
                                                        ];
                                                        $cur = $cfg->location ?? 'hel1';
                                                    @endphp
                                                    @foreach($locs as $code => $label)
                                                        <option value="{{ $code }}" {{ $cur==$code?'selected':'' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-interface')}}</label>
                                                <input type="text" name="interface" class="form-control" value="{{ $cfg->interface ?? 'eth0' }}" placeholder="eth0">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-htoken')}}</label>
                                                <input type="text" name="hetzner_token" class="form-control" value="{{ $cfg->hetzner_token ?? '' }}" placeholder="Hetzner API Token" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-servername')}}</label>
                                                <input type="text" name="server_name" class="form-control" value="{{ $cfg->server_name ?? '' }}" placeholder="my-server" required>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-cfemail')}}</label>
                                                <input type="text" name="cf_email" class="form-control" value="{{ $cfg->cf_email ?? '' }}" placeholder="you@example.com" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-cfkey')}}</label>
                                                <input type="text" name="cf_global_key" class="form-control" value="{{ $cfg->cf_global_key ?? '' }}" placeholder="Cloudflare Global API Key" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-cfzone')}}</label>
                                                <input type="text" name="cf_zone_id" class="form-control" value="{{ $cfg->cf_zone_id ?? '' }}" placeholder="Zone ID" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-cfrecord')}}</label>
                                                <input type="text" name="cf_record_id" class="form-control" value="{{ $cfg->cf_record_id ?? '' }}" placeholder="Record ID" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-serverip-domain')}}</label>
                                                <input type="text" name="domain_name" class="form-control" value="{{ $cfg->domain_name ?? '' }}" placeholder="mc1.example.com" required>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-2">{{__('setting-save')}}</button>
                                </form>

                                <hr>

                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <a href="{{ route('settings.serverip.run') }}" class="btn btn-success"
                                       onclick="return confirm('Run IP rotation now?')">
                                        <i class="ti ti-refresh"></i> {{__('setting-serverip-run')}}
                                    </a>
                                    @if($cfg && $cfg->last_ip)
                                        <span class="badge bg-primary">{{__('setting-serverip-lastip')}}: {{ $cfg->last_ip }}</span>
                                    @endif
                                    @if($cfg && $cfg->last_run_at)
                                        <span class="text-muted">{{ $cfg->last_run_at }}</span>
                                    @endif
                                </div>

                                <label>{{__('setting-serverip-log')}}</label>
                                <pre style="background:#0b1020;color:#a8e6a1;padding:15px;border-radius:8px;min-height:180px;max-height:400px;overflow:auto;white-space:pre-wrap;">{{ $cfg->last_log ?? '—' }}</pre>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
