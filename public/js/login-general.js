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
                    // nik: {
                    //     validators: {
                    //         notEmpty: { message: "NIK is required" },
                    //     },
                    // },
                    // password: {
                    //     validators: {
                    //         notEmpty: { message: "Password is required" },
                    //     },
                    // },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),

                    bootstrap: new FormValidation.plugins.Bootstrap(),
                },
            },
        );

        formEl.on("submit", function (e) {
            e.preventDefault();
            $("#kt_login_signin_submit").trigger("click");
        });

        $("#kt_login_signin_submit").on("click", function (e) {
            e.preventDefault();

            const btn = $(this);
            const formEl = $("#kt_login_signin_form");
            clearErrors(formEl);

            validation.validate().then((status) => {
                if (status === "Valid") {
                    $.ajax({
                        url: (window.auth && window.auth.login) ? window.auth.login : (formEl.attr("action") || "/login"),
                        method: "POST",
                        headers: {
                            "Accept": "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        dataType: "json",
                        data: formEl.serialize(),
                        beforeSend: function () {
                            showLoading(btn);
                        },
                        success: (response) => {
                            window.location.href =
                                response.redirect || window.auth.dashboard;
                        },
                        error: (xhr) => {
                            if (typeof window.turnstile !== "undefined") {
                                window.turnstile.reset(".cf-turnstile");
                            }

                            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                                displayErrors(formEl, xhr.responseJSON.errors);
                            } else if (xhr.status === 419) {
                                showAlert(
                                    "Sesi halaman login telah kedaluwarsa (CSRF expired). Halaman akan dimuat ulang.",
                                    "warning",
                                    () => {
                                        window.location.reload();
                                    }
                                );
                            } else if (xhr.status === 429) {
                                showAlert(
                                    xhr.responseJSON?.message ||
                                    "Terlalu banyak percobaan login. Silakan tunggu 1 menit sebelum mencoba lagi.",
                                    "warning"
                                );
                            } else if (xhr.status === 0) {
                                showAlert(
                                    "Koneksi gagal atau terputus (Network Error). Silakan periksa koneksi Anda atau muat ulang halaman.",
                                    "error"
                                );
                            } else {
                                const errorMsg =
                                    xhr.responseJSON?.message ||
                                    (xhr.status ? `Terjadi kesalahan pada server (Status: ${xhr.status}). Silakan coba lagi.` : "Login gagal, silakan cek username dan password.");
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
