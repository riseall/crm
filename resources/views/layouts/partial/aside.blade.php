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
                                    <a href="{{ route('outlet.index') }}" class="menu-link">
                                        <i class="menu-bullet menu-bullet-dot">
                                            <span></span>
                                        </i>
                                        <span class="menu-text">Outlet</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endhasanyrole
                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="{{ route('dashboard.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <path
                                        d="M5,13 L5,20 C5,20.5522847 5.44771525,21 6,21 L8,21 C8.55228475,21 9,20.5522847 9,20 L9,13 C9,12.4477153 8.55228475,12 8,12 L6,12 C5.44771525,12 5,12.4477153 5,13 Z M10,9 L10,20 C10,20.5522847 10.4477153,21 11,21 L13,21 C13.5522847,21 14,20.5522847 14,20 L14,9 C14,8.44771525 13.5522847,8 13,8 L11,8 C10.4477153,8 10,8.44771525 10,9 Z"
                                        fill="#000000" opacity="0.3" />
                                    <path
                                        d="M19.2928932,4.29289322 L16,4 L16,20 C16,20.5522847 16.4477153,21 17,21 L19,21 C19.5522847,21 20,20.5522847 20,20 L20,5.70710678 C20,5.18633956 19.5951595,4.75550552 19.0761624,4.70740015 L19.2928932,4.29289322 Z M19.2928932,4.29289322 C19.6834175,3.90236893 20.3165825,3.90236893 20.7071068,4.29289322 C21.0976311,4.68341751 21.0976311,5.31658249 20.7071068,5.70710678 L17.7071068,8.70710678 C17.3165825,9.09763107 16.6834175,9.09763107 16.2928932,8.70710678 C15.9023689,8.31658249 15.9023689,7.68341751 16.2928932,7.29289322 L19.2928932,4.29289322 Z"
                                        fill="#000000" />
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
                        <span class="menu-text">Performance Outlet</span>
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
                        <span class="menu-text">Monitoring Selling In</span>
                    </a>
                </li>
                <li class="menu-item menu-item-active" aria-haspopup="true">
                    <a href="{{ route('stock.index') }}" class="menu-link">
                        <span class="svg-icon menu-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <polygon points="0 0 24 0 24 24 0 24" />
                                    <path
                                        d="M4,13 L4,18 C4,18.5522847 4.44771525,19 5,19 L19,19 C19.5522847,19 20,18.5522847 20,18 L20,13 L4,13 Z"
                                        fill="#000000" opacity="0.3" />
                                    <path
                                        d="M4,5 L4,10 C4,10.5522847 4.44771525,11 5,11 L19,11 C19.5522847,11 20,10.5522847 20,10 L20,5 C20,4.44771525 19.5522847,4 19,4 L5,4 C4.44771525,4 4,4.44771525 4,5 Z"
                                        fill="#000000" />
                                    <rect fill="#000000" opacity="0.3" x="9" y="13" width="6" height="2"
                                        rx="1" />
                                    <rect fill="#000000" x="9" y="6" width="6" height="2" rx="1" />
                                </g>
                            </svg>
                        </span>
                        <span class="menu-text">Monitoring Stock GPJ vs Cabang</span>
                    </a>
                </li>
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
