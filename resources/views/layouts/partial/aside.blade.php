<div class="aside aside-left aside-fixed d-flex flex-column flex-row-auto" id="kt_aside">
    <!--begin::Brand-->
    <div class="brand flex-column-auto" id="kt_brand">
        <!--begin::Logo-->
        <a href="{{ route('dashboard.index') }}" class="brand-logo">
            <img alt="Logo" class="max-w-125px" src="{{ asset('images/logo/crm-typoWht.png') }}" />
        </a>
        <!--end::Logo-->
        <!--begin::Toggle-->
        <button class="brand-toggle btn btn-sm px-0" id="kt_aside_toggle">
            <span class="svg-icon svg-icon svg-icon-xl">
                <!--begin::Svg Icon | path:assets/media/svg/icons/Navigation/Angle-double-left.svg-->
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px"
                    height="24px" viewBox="0 0 24 24" version="1.1">
                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <polygon points="0 0 24 0 24 24 0 24" />
                        <path
                            d="M5.29288961,6.70710318 C4.90236532,6.31657888 4.90236532,5.68341391 5.29288961,5.29288961 C5.68341391,4.90236532 6.31657888,4.90236532 6.70710318,5.29288961 L12.7071032,11.2928896 C13.0856821,11.6714686 13.0989277,12.281055 12.7371505,12.675721 L7.23715054,18.675721 C6.86395813,19.08284 6.23139076,19.1103429 5.82427177,18.7371505 C5.41715278,18.3639581 5.38964985,17.7313908 5.76284226,17.3242718 L10.6158586,12.0300721 L5.29288961,6.70710318 Z"
                            fill="#000000" fill-rule="nonzero"
                            transform="translate(8.999997, 11.999999) scale(-1, 1) translate(-8.999997, -11.999999)" />
                        <path
                            d="M10.7071009,15.7071068 C10.3165766,16.0976311 9.68341162,16.0976311 9.29288733,15.7071068 C8.90236304,15.3165825 8.90236304,14.6834175 9.29288733,14.2928932 L15.2928873,8.29289322 C15.6714663,7.91431428 16.2810527,7.90106866 16.6757187,8.26284586 L22.6757187,13.7628459 C23.0828377,14.1360383 23.1103407,14.7686056 22.7371482,15.1757246 C22.3639558,15.5828436 21.7313885,15.6103465 21.3242695,15.2371541 L16.0300699,10.3841378 L10.7071009,15.7071068 Z"
                            fill="#000000" fill-rule="nonzero" opacity="0.3"
                            transform="translate(15.999997, 11.999999) scale(-1, 1) rotate(-270.000000) translate(-15.999997, -11.999999)" />
                    </g>
                </svg>
                <!--end::Svg Icon-->
            </span>
        </button>
        <!--end::Toolbar-->
    </div>
    <!--end::Brand-->
    <!--begin::Aside Menu-->
    <div class="aside-menu-wrapper flex-column-fluid" id="kt_aside_menu_wrapper">
        <!--begin::Menu Container-->
        <div id="kt_aside_menu" class="aside-menu my-4" data-menu-vertical="1" data-menu-scroll="1"
            data-menu-dropdown-timeout="500">
            <!--begin::Menu Nav-->
            <ul class="menu-nav">
                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="javascript:void(0)" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path
                                        d="M5.5,4 L9.5,4 C10.3284271,4 11,4.67157288 11,5.5 L11,6.5 C11,7.32842712 10.3284271,8 9.5,8 L5.5,8 C4.67157288,8 4,7.32842712 4,6.5 L4,5.5 C4,4.67157288 4.67157288,4 5.5,4 Z M14.5,16 L18.5,16 C19.3284271,16 20,16.6715729 20,17.5 L20,18.5 C20,19.3284271 19.3284271,20 18.5,20 L14.5,20 C13.6715729,20 13,19.3284271 13,18.5 L13,17.5 C13,16.6715729 13.6715729,16 14.5,16 Z"
                                        fill="#000000" />
                                    <path
                                        d="M5.5,10 L9.5,10 C10.3284271,10 11,10.6715729 11,11.5 L11,18.5 C11,19.3284271 10.3284271,20 9.5,20 L5.5,20 C4.67157288,20 4,19.3284271 4,18.5 L4,11.5 C4,10.6715729 4.67157288,10 5.5,10 Z M14.5,4 L18.5,4 C19.3284271,4 20,4.67157288 20,5.5 L20,12.5 C20,13.3284271 19.3284271,14 18.5,14 L14.5,14 C13.6715729,14 13,13.3284271 13,12.5 L13,5.5 C13,4.67157288 13.6715729,4 14.5,4 Z"
                                        fill="#000000" opacity="0.3" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Performance Overview</span>
                    </a>
                </li>

                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="{{ route('dashboard.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <rect fill="#000000" opacity="0.3" x="13" y="4" width="3" height="16"
                                        rx="1.5" />
                                    <rect fill="#000000" x="8" y="9" width="3" height="11" rx="1.5" />
                                    <rect fill="#000000" x="18" y="11" width="3" height="9" rx="1.5" />
                                    <rect fill="#000000" x="3" y="13" width="3" height="7" rx="1.5" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Performance Selling Out</span>
                    </a>
                </li>

                <li class="menu-item" aria-haspopup="true">
                    <a href="{{ route('outlet.history') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <path
                                        d="M4.5,11.5 L4.5,19.5 C4.5,20.3284271 5.17157288,21 6,21 L18,21 C18.8284271,21 19.5,20.3284271 19.5,19.5 L19.5,11.5 L4.5,11.5 Z"
                                        fill="#000000" opacity="0.3" />
                                    <path
                                        d="M10,16 L10,21 L14,21 L14,16 C14,15.4477153 13.5522847,15 13,15 L11,15 C10.4477153,15 10,15.4477153 10,16 Z M2,11 L2,12 C2,12.5522847 2.44771525,13 3,13 L21,13 C21.5522847,13 22,12.5522847 22,12 L22,11 C22,10.4477153 21.5522847,10 21,10 L19.5,10 L17.7847771,4.57452685 C17.6302428,4.09958145 17.1877532,3.78 16.6877532,3.78 L7.31224682,3.78 C6.81224682,3.78 6.36975719,4.09958145 6.21522292,4.57452685 L4.5,10 L3,10 C2.44771525,10 2,10.4477153 2,11 Z"
                                        fill="#000000" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Monitoring Outlet Transaksi</span>
                    </a>
                </li>

                <li class="menu-item" aria-haspopup="true">
                    <a href="{{ route('visits.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path
                                        d="M12,2 C8.13,2 5,5.13 5,9 C5,14.25 12,22 12,22 C12,22 19,14.25 19,9 C19,5.13 15.87,2 12,2 Z M12,11.5 C10.62,11.5 9.5,10.38 9.5,9 C9.5,7.62 10.62,6.5 12,6.5 C13.38,6.5 14.5,7.62 14.5,9 C14.5,10.38 13.38,11.5 12,11.5 Z"
                                        fill="#000000" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Kunjungan VS Realisasi</span>
                    </a>
                </li>

                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="javascript:void(0)" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path
                                        d="M9.07117914,12.5710461 L13.8326627,12.5710461 C14.108805,12.5710461 14.3326627,12.3471885 14.3326627,12.0710461 L14.3326627,0.16733734 C14.3326627,-0.108805035 14.108805,-0.33266266 13.8326627,-0.33266266 C13.6282104,-0.33266266 13.444356,-0.208187188 13.3684243,-0.0183579985 L8.6069408,11.8853508 C8.50438409,12.1417426 8.62909204,12.4327278 8.8854838,12.5352845 C8.94454394,12.5589085 9.00756943,12.5710461 9.07117914,12.5710461 Z"
                                        fill="#000000" opacity="0.3"
                                        transform="translate(11.451854, 6.119192) rotate(-270.000000) translate(-11.451854, -6.119192) " />
                                    <path
                                        d="M9.23851648,24.5 L14,24.5 C14.2761424,24.5 14.5,24.2761424 14.5,24 L14.5,12.0962912 C14.5,11.8201488 14.2761424,11.5962912 14,11.5962912 C13.7955477,11.5962912 13.6116933,11.7207667 13.5357617,11.9105959 L8.77427814,23.8143047 C8.67172143,24.0706964 8.79642938,24.3616816 9.05282114,24.4642383 C9.11188128,24.4878624 9.17490677,24.5 9.23851648,24.5 Z"
                                        fill="#000000"
                                        transform="translate(11.500000, 18.000000) scale(1, -1) rotate(-270.000000) translate(-11.500000, -18.000000) " />
                                    <rect fill="#000000" opacity="0.3"
                                        transform="translate(12.000000, 12.000000) rotate(-270.000000) translate(-12.000000, -12.000000) "
                                        x="11" y="2" width="2" height="20" rx="1" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Monitoring Detail Kunjungan</span>
                    </a>
                </li>

                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="{{ route('selling-in.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <rect fill="#000000" opacity="0.3" x="3" y="3" width="2" height="18"
                                        rx="1" />
                                    <rect fill="#000000" x="6" y="5" width="14" height="3.5" rx="1" />
                                    <rect fill="#000000" x="6" y="10.5" width="10" height="3.5"
                                        rx="1" />
                                    <rect fill="#000000" opacity="0.3" x="6" y="16" width="6" height="3.5"
                                        rx="1" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Performance Selling In</span>
                    </a>
                </li>

                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="{{ route('stock.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <path
                                        d="M5,2 L19,2 C20.1045695,2 21,2.8954305 21,4 L21,6 C21,7.1045695 20.1045695,8 19,8 L5,8 C3.8954305,8 3,7.1045695 3,6 L3,4 C3,2.8954305 3.8954305,2 5,2 Z M11,4 C10.4477153,4 10,4.44771525 10,5 C10,5.55228475 10.4477153,6 11,6 L16,6 C16.5522847,6 17,5.55228475 17,5 C17,4.44771525 16.5522847,4 16,4 L11,4 Z M7,6 C7.55228475,6 8,5.55228475 8,5 C8,4.44771525 7.55228475,4 7,4 C6.44771525,4 6,4.44771525 6,5 C6,5.55228475 6.44771525,6 7,6 Z"
                                        fill="#000000" opacity="0.3" />
                                    <path
                                        d="M5,9 L19,9 C20.1045695,9 21,9.8954305 21,11 L21,13 C21,14.1045695 20.1045695,15 19,15 L5,15 C3.8954305,15 3,14.1045695 3,13 L3,11 C3,9.8954305 3.8954305,9 5,9 Z M11,11 C10.4477153,11 10,11.4477153 10,12 C10,12.5522847 10.4477153,13 11,13 L16,13 C16.5522847,13 17,12.5522847 17,12 C17,11.4477153 16.5522847,11 16,11 L11,11 Z M7,13 C7.55228475,13 8,12.5522847 8,12 C8,11.4477153 7.55228475,11 7,11 C6.44771525,11 6,11.4477153 6,12 C6,12.5522847 6.44771525,13 7,13 Z"
                                        fill="#000000" />
                                    <path
                                        d="M5,16 L19,16 C20.1045695,16 21,16.8954305 21,18 L21,20 C21,21.1045695 20.1045695,22 19,22 L5,22 C3.8954305,22 3,21.1045695 3,20 L3,18 C3,16.8954305 3.8954305,16 5,16 Z M11,18 C10.4477153,18 10,18.4477153 10,19 C10,19.5522847 10.4477153,20 11,20 L16,20 C16.5522847,20 17,19.5522847 17,19 C17,18.4477153 16.5522847,18 16,18 L11,18 Z M7,20 C7.55228475,20 8,19.5522847 8,19 C8,18.4477153 7.55228475,18 7,18 C6.44771525,18 6,18.4477153 6,19 C6,19.5522847 6.44771525,20 7,20 Z"
                                        fill="#000000" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Monitoring Stock GPJ vs Cabang</span>
                    </a>
                </li>

                @hasanyrole(['Super Admin', 'Admin IT', 'Marketing Insight'])
                    <li class="menu-item menu-item-submenu" aria-haspopup="true" data-menu-toggle="hover">
                        <a href="javascript:;" class="menu-link menu-toggle">
                            <span class="svg-icon menu-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                    width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <polygon points="0 0 24 0 24 24 0 24" />
                                        <path
                                            d="M12,3 C7.581722,3 4,4.34314575 4,6 C4,7.65685425 7.581722,9 12,9 C16.418278,9 20,7.65685425 20,6 C20,4.34314575 16.418278,3 12,3 Z"
                                            fill="#000000" />
                                        <path
                                            d="M4,10 L4,14 C4,15.6568542 7.581722,17 12,17 C16.418278,17 20,15.6568542 20,14 L20,10 C18.3,11.3 15.3,12 12,12 C8.7,12 5.7,11.3 4,10 Z M4,16 L4,18 C4,19.6568542 7.581722,21 12,21 C16.418278,21 20,19.6568542 20,18 L20,16 C18.3,17.3 15.3,18 12,18 C8.7,18 5.7,17.3 4,16 Z"
                                            fill="#000000" opacity="0.3" />
                                    </g>
                                </svg>
                            </span>
                            <span class="menu-text">Data Master</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="menu-submenu">
                            <i class="menu-arrow"></i>
                            <ul class="menu-subnav">
                                <li class="menu-item menu-item-parent" aria-haspopup="true">
                                    <span class="menu-link">
                                        <span class="menu-text">Data Master</span>
                                    </span>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('users.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Users</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('mapping.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Mapping Coverage</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('product.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Produk</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('top-marketing.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Top Marketing</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('outlet.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Outlet</span>
                                    </a>
                                </li>
                                <li class="menu-item" aria-haspopup="true">
                                    <a href="{{ route('sellout-adjustment.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Adjust Sellout</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endhasanyrole
            </ul>
            <!--end::Menu Nav-->
        </div>
        <!--end::Menu Container-->
    </div>
    <!--end::Aside Menu-->
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuItems = document.querySelectorAll('.menu-item');
        const menuLinks = document.querySelectorAll('.menu-item > .menu-link[href]:not(.menu-toggle)');
        const currentUrl = window.location.origin + window.location.pathname;

        function resetActiveMenu() {
            menuItems.forEach(item => item.classList.remove('menu-item-active', 'menu-item-open'));
        }

        function setActiveMenu(item) {
            item.classList.add('menu-item-active');

            const parentSubMenu = item.parentElement.closest('.menu-item-submenu');
            if (parentSubMenu) {
                parentSubMenu.classList.add('menu-item-active', 'menu-item-open');
            }
        }

        resetActiveMenu();

        let hasActiveMenu = false;

        menuLinks.forEach(link => {
            const href = link.href.split('#')[0];

            if (!hasActiveMenu && href !== 'javascript:;' && currentUrl === href) {
                setActiveMenu(link.closest('.menu-item'));
                hasActiveMenu = true;
            }

            link.addEventListener('click', function() {
                const item = this.closest('.menu-item');

                if (item) {
                    resetActiveMenu();
                    setActiveMenu(item);
                }
            });
        });
    })
</script>
