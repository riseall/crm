"use strict";

(function (window, document, $) {
    var showTimer = null;
    var showDelay = 250;
    var body = document.body;
    var modalId = "crm-inline-loading";
    var modalStyleId = "crm-inline-loading-style";
    var loadingState = {
        ajax: false,
        fetch: 0,
        manual: 0,
        datatables: {},
    };

    function ensureModal() {
        if (document.getElementById(modalStyleId)) {
            return;
        }

        var style = document.createElement("style");
        style.id = modalStyleId;
        style.textContent =
            ".crm-inline-loading{position:fixed;inset:0;z-index:2050;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.5);cursor:wait}" +
            ".crm-inline-loading.is-active{display:flex}" +
            ".crm-inline-loading__dialog{width:min(340px,calc(100vw - 32px));padding:28px 24px;border-radius:10px;background:#fff;text-align:center;box-shadow:0 18px 50px rgba(15,23,42,.24)}" +
            ".crm-hg{width:48px;height:66px}" +
            ".crm-inline-loading__title{margin:18px 0 0;color:#181c32;font-size:15px;font-weight:600}" +
            ".crm-inline-loading__text{margin:6px 0 0;color:#7e8299;font-size:12px}" +
            ".hg-body{animation:hg-flip 3s ease-in-out infinite;transform-origin:24px 33px}" +
            ".hg-top{animation:hg-drain 3s ease-in-out infinite}" +
            ".hg-bot{animation:hg-fill 3s ease-in-out infinite}" +
            ".hg-p1{animation:hg-drop 3s 0s ease-in infinite}" +
            ".hg-p2{animation:hg-drop 3s .7s ease-in infinite}" +
            "@keyframes hg-drain{0%,8%{d:path('M14 10 Q24 11 34 10 L28 27 Q24 28.5 20 27 Z')}74%,82%{d:path('M23 26 Q24 26.5 25 26 L25 27 Q24 28 23 27 Z')}96%,100%{d:path('M14 10 Q24 11 34 10 L28 27 Q24 28.5 20 27 Z')}}" +
            "@keyframes hg-fill{0%,8%{d:path('M23 39 Q24 39 25 39 L25 38 Q24 37.5 23 38 Z')}74%,82%{d:path('M14 56 Q24 56 34 56 L28 39 Q24 37.5 20 39 Z')}96%,100%{d:path('M23 39 Q24 39 25 39 L25 38 Q24 37.5 23 38 Z')}}" +
            "@keyframes hg-flip{0%,78%{transform:rotate(0deg)}90%,100%{transform:rotate(180deg)}}" +
            "@keyframes hg-drop{0%{cy:28;r:.9;opacity:1}100%{cy:39;r:.4;opacity:0}}";
        document.head.appendChild(style);
    }

    function getModal() {
        var modal = document.getElementById(modalId);

        if (modal) {
            return modal;
        }

        modal = document.createElement("div");
        modal.id = modalId;
        modal.className = "crm-inline-loading";
        modal.setAttribute("role", "status");
        modal.setAttribute("aria-live", "polite");
        modal.innerHTML =
            '<div class="crm-inline-loading__dialog">' +
            '<svg class="crm-hg" viewBox="0 0 48 66" fill="none" aria-hidden="true">' +
            '<g class="hg-body">' +
            '<path d="M14 10 Q24 11 34 10 L28 27 Q24 29 20 27 Z" fill="none" stroke="#6C6AF6" stroke-width=".9" stroke-linejoin="round"/>' +
            '<path d="M14 56 Q24 55 34 56 L28 39 Q24 37 20 39 Z" fill="none" stroke="#6C6AF6" stroke-width=".9" stroke-linejoin="round"/>' +
            '<line x1="17" y1="9" x2="31" y2="9" stroke="#6C6AF6" stroke-width="1.5" stroke-linecap="round"/>' +
            '<line x1="17" y1="57" x2="31" y2="57" stroke="#6C6AF6" stroke-width="1.5" stroke-linecap="round"/>' +
            '<line x1="12" y1="9" x2="14" y2="9" stroke="#6C6AF6" stroke-width=".7" stroke-linecap="round" opacity=".5"/>' +
            '<line x1="34" y1="9" x2="36" y2="9" stroke="#6C6AF6" stroke-width=".7" stroke-linecap="round" opacity=".5"/>' +
            '<line x1="12" y1="57" x2="14" y2="57" stroke="#6C6AF6" stroke-width=".7" stroke-linecap="round" opacity=".5"/>' +
            '<line x1="34" y1="57" x2="36" y2="57" stroke="#6C6AF6" stroke-width=".7" stroke-linecap="round" opacity=".5"/>' +
            '<circle cx="24" cy="33" r="1.2" fill="none" stroke="#6C6AF6" stroke-width=".8"/>' +
            '<path class="hg-top" fill="#AFA9EC" opacity=".8"/>' +
            '<path class="hg-bot" fill="#AFA9EC" opacity=".8"/>' +
            '<circle class="hg-p1" cx="24" cy="28" fill="#7F77DD"/>' +
            '<circle class="hg-p2" cx="24" cy="28" fill="#7F77DD"/>' +
            "</g></svg>" +
            '<p class="crm-inline-loading__title">Sedang memproses</p>' +
            '<p class="crm-inline-loading__text">Mohon tunggu sampai data selesai ditampilkan.</p>' +
            "</div>";
        document.body.appendChild(modal);

        return modal;
    }

    function showModal() {
        ensureModal();
        getModal().classList.add("is-active");
    }

    function hideModal() {
        var modal = document.getElementById(modalId);

        if (modal) {
            modal.classList.remove("is-active");
        }
    }

    function showPageLoader() {
        if (!body) {
            return;
        }

        body.classList.add("page-loading");
    }

    function hidePageLoader() {
        if (!body) {
            return;
        }

        body.classList.remove("page-loading");
    }

    function hasInlineJobs() {
        var hasDataTable = Object.keys(loadingState.datatables).some(
            function (key) {
                return loadingState.datatables[key];
            },
        );

        return (
            loadingState.ajax ||
            loadingState.fetch > 0 ||
            loadingState.manual > 0 ||
            hasDataTable
        );
    }

    function refreshInlineLoading() {
        var modal = document.getElementById(modalId);
        var isActive = modal && modal.classList.contains("is-active");

        if (!hasInlineJobs()) {
            if (showTimer) {
                window.clearTimeout(showTimer);
                showTimer = null;
            }

            hideModal();
            return;
        }

        if (showTimer || isActive) {
            return;
        }

        showTimer = window.setTimeout(function () {
            showTimer = null;

            if (hasInlineJobs()) {
                showModal();
            }
        }, showDelay);
    }

    function start() {
        loadingState.manual += 1;
        refreshInlineLoading();
    }

    function stop() {
        loadingState.manual = Math.max(loadingState.manual - 1, 0);
        refreshInlineLoading();
    }

    function setAjaxLoading(isLoading) {
        loadingState.ajax = isLoading;
        refreshInlineLoading();
    }

    function startFetch() {
        loadingState.fetch += 1;
        refreshInlineLoading();
    }

    function stopFetch() {
        loadingState.fetch = Math.max(loadingState.fetch - 1, 0);
        refreshInlineLoading();
    }

    function setDataTableLoading(settings, isLoading) {
        var key =
            settings &&
            (settings.sTableId ||
                settings.sInstance ||
                (settings.nTable && settings.nTable.id));

        if (!key) {
            key = "default";
        }

        if (isLoading) {
            loadingState.datatables[key] = true;
        } else {
            delete loadingState.datatables[key];
        }

        refreshInlineLoading();
    }

    function reset() {
        loadingState.ajax = false;
        loadingState.fetch = 0;
        loadingState.manual = 0;
        loadingState.datatables = {};

        if (showTimer) {
            window.clearTimeout(showTimer);
            showTimer = null;
        }

        hideModal();
    }

    function resetAll() {
        reset();
        hidePageLoader();
    }

    function track(job) {
        start();

        return Promise.resolve(job)
            .then(function (result) {
                stop();
                return result;
            })
            .catch(function (error) {
                stop();
                throw error;
            });
    }

    function shouldIgnoreNavigation(link) {
        if (
            !link ||
            link.target === "_blank" ||
            link.hasAttribute("download")
        ) {
            return true;
        }

        if (
            link.matches(
                "[data-toggle], [data-dismiss], [data-target], [data-fancybox], .dropdown-toggle, .nav-link",
            )
        ) {
            return true;
        }

        var href = link.getAttribute("href");

        if (!href || href === "#" || href.indexOf("javascript:") === 0) {
            return true;
        }

        if (href.charAt(0) === "#") {
            return true;
        }

        var url = new URL(href, window.location.href);

        return url.origin !== window.location.origin;
    }

    if ($) {
        $(document)
            .ajaxStart(function () {
                setAjaxLoading(true);
            })
            .ajaxStop(function () {
                setAjaxLoading(false);
            })
            .on("processing.dt", function (_event, settings, processing) {
                setDataTableLoading(settings, processing);
            });
    }

    if (typeof window.fetch === "function") {
        var nativeFetch = window.fetch.bind(window);

        window.fetch = function () {
            startFetch();

            return nativeFetch
                .apply(null, arguments)
                .then(function (response) {
                    stopFetch();
                    return response;
                })
                .catch(function (error) {
                    stopFetch();
                    throw error;
                });
        };
    }

    document.addEventListener(
        "submit",
        function (event) {
            window.setTimeout(function () {
                if (!event.defaultPrevented) {
                    showPageLoader();
                }
            }, 0);
        },
        true,
    );

    document.addEventListener(
        "click",
        function (event) {
            var target = event.target;
            var link = target && target.closest ? target.closest("a") : null;

            if (event.defaultPrevented || shouldIgnoreNavigation(link)) {
                return;
            }

            window.setTimeout(function () {
                if (!event.defaultPrevented) {
                    showPageLoader();
                }
            }, 0);
        },
        true,
    );

    window.addEventListener("beforeunload", showPageLoader);
    // ponytail: pageshow only resets active state if restored from BFCache (persisted). Normal page loads must keep loadingState intact for AJAX calls started on DOMReady.
    window.addEventListener("pageshow", function (event) {
        hidePageLoader();
        if (event && event.persisted) {
            reset();
        }
    });

    window.CrmLoading = {
        start: start,
        stop: stop,
        reset: reset,
        track: track,
        startPage: showPageLoader,
        stopPage: hidePageLoader,
    };
})(window, document, window.jQuery);
