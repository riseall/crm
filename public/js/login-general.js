"use strict";

const KTLogin = (function () {
    let _login;

    // Fungsi untuk menampilkan alert
    const showAlert = (message, icon = "error", callback = null) => {
        swal.fire({
            text: message,
            icon: icon,
            buttonsStyling: false,
            confirmButtonText: "Ok, got it!",
            customClass: {
                confirmButton: "btn font-weight-bold btn-light-primary",
            },
        }).then(() => {
            KTUtil.scrollTop();
            if (callback) callback();
        });
    };

    // Fungsi untuk menghapus error sebelumnya agar tidak menumpuk
    const clearErrors = (formEl) => {
        formEl.find(".is-invalid").removeClass("is-invalid");
        formEl.find(".invalid-feedback.ajax-error").remove();
    };

    // Fungsi untuk menampilkan error dari Laravel ke bawah input
    const displayErrors = (formEl, errors) => {
        $.each(errors, function (field, messages) {
            const input = formEl.find(`[name="${field}"]`);
            if (input.length) {
                input.addClass("is-invalid");
                input.after(
                    `<div class="invalid-feedback text-left ajax-error">${messages[0]}</div>`,
                );
            }
        });
    };

    // Fungsi untuk memunculkan loading pada tombol
    const showLoading = (btn) => {
        // Menambahkan class spinner bawaan Metronic dan men-disable tombol
        btn.addClass("spinner spinner-right spinner-white pr-15 disabled").prop(
            "disabled",
            true,
        );
    };

    // Fungsi untuk menghilangkan loading pada tombol
    const hideLoading = (btn) => {
        // Menghapus class spinner dan mengaktifkan tombol kembali
        btn.removeClass(
            "spinner spinner-right spinner-white pr-15 disabled",
        ).prop("disabled", false);
    };

    // Fungsi untuk mengganti tampilan form
    const showForm = (formType) => {
        _login.removeClass("login-forgot-on login-signin-on login-signup-on");
        _login.addClass(`login-${formType}-on`);

        KTUtil.animateClass(
            KTUtil.getById(`kt_login_${formType}_form`),
            "animate__animated animate__backInUp",
        );
    };

    // --- Form Handlers ---

    const handleSignInForm = () => {
        const formId = "kt_login_signin_form";
        const formEl = $(`#${formId}`);

        const validation = FormValidation.formValidation(
            KTUtil.getById(formId),
            {
                fields: {
                    username: {
                        validators: {
                            notEmpty: { message: "Username is required" },
                        },
                    },
                    password: {
                        validators: {
                            notEmpty: { message: "Password is required" },
                        },
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                },
            },
        );

        $("#kt_login_signin_submit").on("click", function (e) {
            e.preventDefault();

            const btn = $(this);
            const formEl = $("#kt_login_signin_form");
            clearErrors(formEl);

            validation.validate().then((status) => {
                if (status === "Valid") {
                    $.ajax({
                        url: "/login",
                        method: "POST",
                        data: formEl.serialize(),
                        beforeSend: function () {
                            showLoading(btn);
                        },
                        success: (response) => {
                            window.location.href =
                                response.redirect || "/dashboard";
                        },
                        error: (xhr) => {
                            if (xhr.status === 422 && xhr.responseJSON.errors) {
                                displayErrors(formEl, xhr.responseJSON.errors);
                            } else {
                                const errorMsg =
                                    xhr.responseJSON?.message ||
                                    "Login gagal, silakan cek username dan password.";
                                showAlert(errorMsg, "error");
                            }
                        },
                        complete: function () {
                            hideLoading(btn);
                        },
                    });
                } else {
                    showAlert(
                        "Sorry, looks like there are some errors detected, please try again.",
                    );
                }
            });
        });

        $("#kt_login_forgot").on("click", (e) => {
            e.preventDefault();
            showForm("forgot");
        });
        $("#kt_login_signup").on("click", (e) => {
            e.preventDefault();
            showForm("signup");
        });
    };

    const handleSignUpForm = () => {
        const formId = "kt_login_signup_form";
        const formEl = $(`#${formId}`);
        const formDOM = KTUtil.getById(formId);

        const validation = FormValidation.formValidation(formDOM, {
            fields: {
                firstname: {
                    validators: {
                        notEmpty: { message: "First name is required" },
                    },
                },
                lastname: {
                    validators: {
                        notEmpty: { message: "Last name is required" },
                    },
                },
                username: {
                    validators: {
                        notEmpty: { message: "Username is required" },
                    },
                },
                email: {
                    validators: {
                        notEmpty: { message: "Email address is required" },
                        emailAddress: {
                            message: "The value is not a valid email address",
                        },
                    },
                },
                password: {
                    validators: {
                        notEmpty: { message: "The password is required" },
                    },
                },
                password_confirmation: {
                    validators: {
                        notEmpty: {
                            message: "The password confirmation is required",
                        },
                        identical: {
                            compare: () =>
                                formDOM.querySelector('[name="password"]')
                                    .value,
                            message:
                                "The password and its confirm are not the same",
                        },
                    },
                },
                agree: {
                    validators: {
                        notEmpty: {
                            message: "You must accept the terms and conditions",
                        },
                    },
                },
            },
            plugins: {
                trigger: new FormValidation.plugins.Trigger(),
                bootstrap: new FormValidation.plugins.Bootstrap(),
            },
        });

        $("#kt_login_signup_submit").on("click", function (e) {
            const btn = $(this);
            e.preventDefault();
            clearErrors(formEl);

            validation.validate().then((status) => {
                if (status === "Valid") {
                    let formData = formEl.serializeArray();

                    $.ajax({
                        url: "/register",
                        method: "POST",
                        data: $.param(formData),
                        beforeSend: function () {
                            showLoading(btn);
                        },
                        success: (response) => {
                            showAlert(
                                "Registrasi berhasil! Silakan login.",
                                "success",
                                () => showForm("signin"),
                            );
                        },
                        error: (xhr) => {
                            if (xhr.status === 422 && xhr.responseJSON.errors) {
                                displayErrors(formEl, xhr.responseJSON.errors);
                            } else {
                                const errorMsg =
                                    xhr.responseJSON?.message ||
                                    "Registrasi gagal, silakan cek data Anda.";
                                showAlert(errorMsg, "error");
                            }
                        },
                        complete: function () {
                            hideLoading(btn);
                        },
                    });
                } else {
                    showAlert(
                        "Sorry, looks like there are some errors detected, please try again.",
                    );
                }
            });
        });

        $("#kt_login_signup_cancel").on("click", (e) => {
            e.preventDefault();
            showForm("signin");
        });
    };

    const handleForgotForm = () => {
        const formId = "kt_login_forgot_form";
        const formEl = $(`#${formId}`);

        const validation = FormValidation.formValidation(
            KTUtil.getById(formId),
            {
                fields: {
                    email: {
                        validators: {
                            notEmpty: { message: "Email address is required" },
                            emailAddress: {
                                message:
                                    "The value is not a valid email address",
                            },
                        },
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                },
            },
        );

        $("#kt_login_forgot_submit").on("click", function (e) {
            const btn = $(this);
            e.preventDefault();

            clearErrors(formEl);

            validation.validate().then((status) => {
                if (status === "Valid") {
                    $.ajax({
                        url: "/forgot-password",
                        method: "POST",
                        data: formEl.serialize(),
                        beforeSend: function () {
                            showLoading(btn);
                        },
                        success: (response) => {
                            const successMsg =
                                response.status ||
                                "Tautan reset password telah dikirim ke email Anda.";

                            showAlert(successMsg, "success", () => {
                                showForm("signin");
                                formEl[0].reset();
                            });
                        },
                        error: (xhr) => {
                            if (xhr.status === 422 && xhr.responseJSON.errors) {
                                displayErrors(formEl, xhr.responseJSON.errors);
                            } else {
                                const errorMsg =
                                    xhr.responseJSON?.message ||
                                    "Terjadi kesalahan, silakan coba lagi.";
                                showAlert(errorMsg, "error");
                            }
                        },
                        complete: function () {
                            hideLoading(btn);
                        },
                    });
                } else {
                    showAlert(
                        "Sorry, looks like there are some errors detected, please try again.",
                    );
                }
            });
        });

        $("#kt_login_forgot_cancel").on("click", (e) => {
            e.preventDefault();
            showForm("signin");
        });
    };

    // --- Public Functions ---
    return {
        init: function () {
            _login = $("#kt_login");

            handleSignInForm();
            handleSignUpForm();
            handleForgotForm();
        },
    };
})();

// Class Initialization
jQuery(document).ready(function () {
    KTLogin.init();
});
