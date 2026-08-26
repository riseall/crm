@extends('layouts.guest', ['title' => 'Login'])

@section('content')
    <div class="login login-3 login-signin-on d-flex flex-row-fluid" id="kt_login">
        <div class="d-flex flex-center bgi-size-cover bgi-no-repeat flex-row-fluid"
            style="background-image: url('{{ asset('images/bg-2.jpg') }}');">
            <div class="login-form text-center text-white p-7 position-relative overflow-hidden">
                <!--begin::Login Header-->
                <div class="d-flex flex-center mb-15">
                    <a href="#">
                        <img src="{{ asset('images/logo/crm-typoWht.png') }}" class="max-h-100px" alt="" />
                    </a>
                </div>
                <!--end::Login Header-->
                <!--begin::Login Sign in form-->
                <div class="login-signin">
                    <div class="mb-20">
                        <h3 class="opacity-40 font-weight-normal">Sign In</h3>
                        <p class="opacity-40">Enter your details to login to your account:</p>
                    </div>
                    <form class="form" id="kt_login_signin_form" method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group">
                            <input
                                class="form-control h-auto text-white bg-white-o-5 rounded border-0 py-4 px-8 @error('nik') @enderror"
                                type="text" placeholder="NIK" name="nik" autocomplete="off"
                                value="{{ old('nik') }}" />
                            @error('nik')
                                <div class="invalid-feedback text-left" style="display: block;">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input
                                    class="form-control h-auto text-white bg-white-o-5 rounded-left border-0 py-4 px-8 @error('password') @enderror"
                                    type="password" id="password" placeholder="Password" name="password" />
                                <div class="input-group-append rounded-right">
                                    <span id="togglePassword" onclick="togglePassword()"
                                        class="input-group-text cursor-pointer border-0 bg-white-o-5">
                                        <i class="far fa-eye text-white bg-white-o-5 opacity-70" id="eyeIcon"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group d-flex flex-wrap justify-content-between align-items-center px-8 opacity-60">
                            <label class="checkbox checkbox-outline checkbox-white text-white m-0">
                                <input type="checkbox" name="remember" />Remember me
                                <span></span>
                            </label>
                        </div>
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.key') }}">
                        </div>
                        <div class="invalid-feedback text-left text-danger @error('cf-turnstile-response') d-block @enderror"
                            data-error-for="cf-turnstile-response">
                            @error('cf-turnstile-response')
                                {{ $message }}
                            @enderror
                        </div>
                        <div class="form-group text-center mt-10">
                            <button id="kt_login_signin_submit" class="btn btn-pill btn-primary opacity-90 px-15 py-3">Sign
                                In</button>
                        </div>
                    </form>
                </div>
                <!--end::Login Sign in form-->
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.auth = {
            login: "{{ route('login') }}",
            dashboard: "{{ route('dashboard.index') }}"
        }
    </script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = "far fa-eye-slash";
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = "far fa-eye";
            }
        }
    </script>
@endpush
