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
                                class="form-control h-auto text-white bg-white-o-5 rounded border-0 py-4 px-8 @error('nik') is-invalid @enderror"
                                type="text" placeholder="NIK" name="nik" autocomplete="off"
                                value="{{ old('nik') }}" />
                            @error('nik')
                                <div class="invalid-feedback text-left">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <div class="input-group">
                                <input
                                    class="form-control h-auto text-white bg-white-o-5 rounded-left border-0 py-4 px-8 @error('password') is-invalid @enderror"
                                    type="password" id="password" placeholder="Password" name="password" />
                                <div class="input-group-append rounded-right">
                                    <span id="togglePassword" onclick="togglePassword()"
                                        class="input-group-text cursor-pointer border-0 bg-white-o-5">
                                        <i class="far fa-eye text-white bg-white-o-5 opacity-70" id="eyeIcon"></i>
                                    </span>
                                </div>
                            </div>
                            @error('password')
                                <div class="invalid-feedback text-left">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group d-flex flex-wrap justify-content-between align-items-center px-8 opacity-60">
                            <label class="checkbox checkbox-outline checkbox-white text-white m-0">
                                <input type="checkbox" name="remember" />Remember me
                                <span></span>
                            </label>
                            {{-- <a href="javascript:;" id="kt_login_forgot" class="text-white font-weight-bold">Forget Password
                                ?</a> --}}
                        </div>
                        {{-- <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.key') }}"> --}}
                </div>

                {{-- @error('cf-turnstile-response')
                            <span class="text-danger" style="color: red; font-size: 0.875rem;">
                                {{ $message }}
                            </span>
                        @enderror --}}
                <div class="form-group text-center mt-10">
                    <button id="kt_login_signin_submit" class="btn btn-pill btn-primary opacity-90 px-15 py-3">Sign
                        In</button>
                </div>
                </form>
                {{-- <div class="mt-10">
                        <span class="opacity-40 mr-4">Don't have an account yet?</span>
                        <a href="javascript:;" id="kt_login_signup" class="text-white opacity-30 font-weight-normal">Sign
                            Up</a>
                    </div> --}}
            </div>
            <!--end::Login Sign in form-->
            <!--begin::Login Sign up form-->
            <div class="login-signup">
                <div class="mb-20">
                    <h3 class="opacity-40 font-weight-normal">Sign Up</h3>
                    <p class="opacity-40">Enter your details to create your account</p>
                </div>
                <form class="form text-center" id="kt_login_signup_form" method="POST" action="#">
                    @csrf
                    <div class="form-group">
                        <input
                            class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8 @error('firstname') is-invalid @enderror"
                            type="text" placeholder="Fullname" name="firstname" value="{{ old('firstname') }}" />
                        @error('firstname')
                            <div class="invalid-feedback text-left">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <input
                            class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8 @error('email') is-invalid @enderror"
                            type="text" placeholder="Email" name="email" autocomplete="off"
                            value="{{ old('email') }}" />
                        @error('email')
                            <div class="invalid-feedback text-left">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <input
                            class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8 @error('password') is-invalid @enderror"
                            type="password" placeholder="Password" name="password" />
                        @error('password')
                            <div class="invalid-feedback text-left">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <input class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8"
                            type="password" placeholder="Confirm Password" name="password_confirmation" />
                    </div>
                    <div class="form-group text-left px-8">
                        <label
                            class="checkbox checkbox-outline checkbox-white opacity-60 text-white m-0 @error('agree') text-danger @enderror">
                            <input type="checkbox" name="agree" />I Agree the
                            <a href="#" class="text-white font-weight-bold">terms and conditions</a>.
                            <span></span>
                        </label>
                        @error('agree')
                            <div class="text-danger mt-1" style="font-size: 0.9rem;">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted text-center"></div>
                    </div>
                    <div class="form-group">
                        <button id="kt_login_signup_submit" class="btn btn-pill btn-primary opacity-90 px-15 py-3 m-2">Sign
                            Up</button>
                        <button id="kt_login_signup_cancel"
                            class="btn btn-pill btn-outline-white opacity-70 px-15 py-3 m-2">Cancel</button>
                    </div>
                </form>
            </div>
            <!--end::Login Sign up form-->
            <!--begin::Login forgot password form-->
            <div class="login-forgot">
                <div class="mb-20">
                    <h3 class="opacity-40 font-weight-normal">Forgotten Password ?</h3>
                    <p class="opacity-40">Enter your email to reset your password</p>
                </div>
                <form class="form" id="kt_login_forgot_form" method="POST" action="#">
                    @csrf
                    <div class="form-group mb-10">
                        <input
                            class="form-control h-auto text-white bg-white-o-5 rounded-pill border-0 py-4 px-8 @error('email') is-invalid @enderror"
                            type="text" placeholder="Email" name="email" autocomplete="off"
                            value="{{ old('email') }}" />
                        @error('email')
                            <div class="invalid-feedback text-left">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <button id="kt_login_forgot_submit"
                            class="btn btn-pill btn-primary opacity-90 px-15 py-3 m-2">Request</button>
                        <button id="kt_login_forgot_cancel"
                            class="btn btn-pill btn-outline-white opacity-70 px-15 py-3 m-2">Cancel</button>
                    </div>
                </form>
            </div>
            <!--end::Login forgot password form-->
        </div>
    </div>
    </div>
@endsection

@push('scripts')
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
