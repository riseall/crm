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
        formEl
            .find("[data-error-for]")
            .removeClass("d-block")
            .text("");
    };

    // Fungsi untuk menampilkan error dari Laravel ke bawah input
    const displayErrors = (formEl, errors) => {
        $.each(errors, function (field, messages) {
            const input = formEl.find(`[name="${field}"]`);
            const feedback = formEl.find(`[data-error-for="${field}"]`);

            if (input.length) {
                input.addClass("is-invalid");
            }

            if (feedback.length) {
                feedback.text(messages[0]).addClass("d-block");
            } else if (input.length) {
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
                        url: window.auth.login,
                        method: "POST",
                        data: formEl.serialize(),
                        beforeSend: function () {
                            showLoading(btn);
                        },
                        success: (response) => {
                            window.location.href =
                                response.redirect || window.auth.dashboard;
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

    // --- Public Functions ---
    return {
        init: function () {
            _login = $("#kt_login");

            handleSignInForm();
        },
    };
})();

// Class Initialization
jQuery(document).ready(function () {
    KTLogin.init();
});
