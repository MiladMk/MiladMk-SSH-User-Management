@extends('layouts.master')
@section('title','MiladMk - '.__('setting-iprotate-menu'))
@section('content')
    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">{{__('setting-iprotate-menu')}}</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        @include('layouts.setting_menu')
                        <div class="tab-content" id="myTabContent">
                            <div class="card-body">

                                @if(session('alert'))
                                    <div class="alert alert-info" role="alert">{{ session('alert') }}</div>
                                @endif

                                <div class="alert alert-warning" role="alert">{{__('setting-iprotate-desc')}}</div>

                                <form action="{{ route('settings.iprotate') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-token')}}</label>
                                                <input type="text" name="api_token" class="form-control" placeholder="Cloudflare API Token" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-zone')}}</label>
                                                <input type="text" name="zone_id" class="form-control" placeholder="Zone ID" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-record')}}</label>
                                                <input type="text" name="record_name" class="form-control" placeholder="mc1.example.com" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-interval')}}</label>
                                                <input type="number" name="interval_minutes" class="form-control" value="60" min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-iplist')}}</label>
                                                <textarea name="ip_list" class="form-control" rows="4" placeholder="1.2.3.4&#10;5.6.7.8&#10;9.10.11.12" required></textarea>
                                                <small>{{__('setting-iprotate-iplist-desc')}}</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('setting-iprotate-mode')}}</label>
                                                <select name="mode" class="form-select">
                                                    <option value="round_robin">{{__('setting-iprotate-mode-rr')}}</option>
                                                    <option value="random">{{__('setting-iprotate-mode-rand')}}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch p-0 mt-4">
                                                <input class="form-check-input h4 position-relative m-0" type="checkbox" role="switch" name="proxied" id="proxied">
                                                <label class="form-check-label ms-2" for="proxied">{{__('setting-iprotate-proxied')}}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch p-0 mt-4">
                                                <input class="form-check-input h4 position-relative m-0" type="checkbox" role="switch" name="status" id="status" value="active" checked>
                                                <label class="form-check-label ms-2" for="status">{{__('setting-iprotate-active')}}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-2" name="submit">{{__('setting-save')}}</button>
                                </form>

                                <hr>

                                <h5 class="mb-3">{{__('setting-iprotate-list-title')}}</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th>{{__('setting-iprotate-record')}}</th>
                                            <th>{{__('setting-iprotate-mode')}}</th>
                                            <th>{{__('setting-iprotate-interval')}}</th>
                                            <th>{{__('setting-iprotate-lastip')}}</th>
                                            <th>{{__('setting-iprotate-status')}}</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @forelse($rotators as $r)
                                            <tr>
                                                <td>{{ $r->record_name }}</td>
                                                <td>{{ $r->mode === 'random' ? __('setting-iprotate-mode-rand') : __('setting-iprotate-mode-rr') }}</td>
                                                <td>{{ $r->interval_minutes }} min</td>
                                                <td>{{ $r->last_ip ?: '—' }}</td>
                                                <td>
                                                    @if($r->status === 'active')
                                                        <span class="badge bg-success">{{__('setting-iprotate-active')}}</span>
                                                    @else
                                                        <span class="badge bg-secondary">off</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('settings.iprotate.now', ['id' => $r->id]) }}" class="btn btn-sm btn-success">{{__('setting-iprotate-now')}}</a>
                                                    <a href="{{ route('settings.iprotate.delete', ['id' => $r->id]) }}" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">{{__('setting-iprotate-delete')}}</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">—</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
