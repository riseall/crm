@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="row">
                <div class="col-lg-7">
                    <!--begin::Mixed Widget 19-->
                    <div class="card card-custom gutter-b">
                        <!--begin::Body-->
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between flex-grow-1">
                                <div class="mr-2">
                                    <h3 class="font-weight-bolder">Create CRM Reports</h3>
                                    <div class="text-muted font-size-lg mt-2">Create CRM Report for month - April 2017</div>
                                </div>
                                <div class="font-weight-boldest font-size-h1 text-warning">$24,200</div>
                            </div>
                            <div class="pt-8">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="text-muted font-weight-bold mr-2">Sale Progress</div>
                                    <div class="text-muted font-weight-bold">60%</div>
                                </div>
                                <div class="progress bg-light-warning progress-xs">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 60%;"
                                        aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        <!--end::Body-->
                    </div>
                    <!--end::Mixed Widget 19-->
                </div>
                <div class="col-lg-5 d-flex">
                    <div class="card card-custom gutter-b flex-grow-1">
                        <div class="card-body">
                            <span class="svg-icon svg-icon-3x svg-icon-success">
                                <!--begin::Svg Icon | path:assets/media/svg/icons/Communication/Group.svg-->
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                    width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <polygon points="0 0 24 0 24 24 0 24"></polygon>
                                        <path
                                            d="M18,14 C16.3431458,14 15,12.6568542 15,11 C15,9.34314575 16.3431458,8 18,8 C19.6568542,8 21,9.34314575 21,11 C21,12.6568542 19.6568542,14 18,14 Z M9,11 C6.790861,11 5,9.209139 5,7 C5,4.790861 6.790861,3 9,3 C11.209139,3 13,4.790861 13,7 C13,9.209139 11.209139,11 9,11 Z"
                                            fill="#000000" fill-rule="nonzero" opacity="0.3"></path>
                                        <path
                                            d="M17.6011961,15.0006174 C21.0077043,15.0378534 23.7891749,16.7601418 23.9984937,20.4 C24.0069246,20.5466056 23.9984937,21 23.4559499,21 L19.6,21 C19.6,18.7490654 18.8562935,16.6718327 17.6011961,15.0006174 Z M0.00065168429,20.1992055 C0.388258525,15.4265159 4.26191235,13 8.98334134,13 C13.7712164,13 17.7048837,15.2931929 17.9979143,20.2 C18.0095879,20.3954741 17.9979143,21 17.2466999,21 C13.541124,21 8.03472472,21 0.727502227,21 C0.476712155,21 -0.0204617505,20.45918 0.00065168429,20.1992055 Z"
                                            fill="#000000" fill-rule="nonzero"></path>
                                    </g>
                                </svg>
                                <!--end::Svg Icon-->
                            </span>
                            <div class="text-dark font-weight-bolder font-size-h2 mt-3">8,600</div>
                            <a href="#" class="text-muted text-hover-primary font-weight-bold font-size-lg mt-1">New
                                Customers</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-custom gutter-b">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Column Chart</h3>
                    </div>
                </div>
                <div class="card-body">
                    <!--begin::Chart-->
                    <div id="chart_2"></div>
                    <!--end::Chart-->
                </div>
            </div>
        </div>
        <div class="col-lg-5 d-flex">
            <div class="card card-custom gutter-b flex-grow-1">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Radar Chart</h3>
                    </div>
                </div>
                <div class="card-body d-flex">
                    <div id="chart_14" style="width:100%;"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-4 d-flex">
            <!--begin::Card-->
            <div class="card card-custom gutter-b flex-grow-1">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Radial Bar Chart</h3>
                    </div>
                </div>
                <div class="card-body d-flex">
                    <!--begin::Chart-->
                    <div id="chart_13" style="width:100%;"></div>
                    <!--end::Chart-->
                </div>
            </div>
            <!--end::Card-->
        </div>
        <div class="col-lg-8">
            <!--begin::Card-->
            <div class="card card-custom gutter-b">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="card-label">Line Chart</h3>
                    </div>
                </div>
                <div class="card-body">
                    <!--begin::Chart-->
                    <div id="chart_3"></div>
                    <!--end::Chart-->
                </div>
            </div>
            <!--end::Card-->
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/charts/apexcharts.js') }}"></script>
@endpush
