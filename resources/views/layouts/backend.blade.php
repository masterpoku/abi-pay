<!DOCTYPE html>

<html lang="en" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Payment Abitour</title>

    <meta name="description" content="" />


    @yield('top')

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('templateAdmin') }}/assets/img/icons/brands/asana.png" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->
    {{-- <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.css" rel="stylesheet"> --}}
    <link rel="stylesheet" href="{{ asset('templateAdmin') }}/air-datepicker/dist/css/datepicker.css">

    <!-- Helpers -->
    <script src="{{ asset('templateAdmin') }}/assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('templateAdmin') }}/assets/js/config.js"></script>


</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->

            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.html" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <img src="{{ asset('templateAdmin') }}/assets/img/icons/brands/asana.png" width="30" alt="Payment Abitour">
                        </span>
                        <span class="app-brand-text demo menu-text fw-bolder ms-2" style="font-size: 24px; text-transform: capitalize">Abitour Payment</span>
                    </a>

                    <a href="#" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item {{ request()->is('dashboard.index*') ? 'active' : '' }}">
                        <a href="{{ route('dashboard.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">Pages</span>
                    </li>
                    <li class="menu-item {{ request()->is('payment*') ? 'active' : '' }}">
                        <a href="{{ route('payment.index') }}" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-time"></i>
                            <div data-i18n="Blast Message">History Pembayaran</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->is('report*') ? 'active' : '' }}">
                        <a href="{{ route('report.index') }}" class="menu-link">
                            <i class='menu-icon notepad bx bxs-notepad'></i>
                            <div data-i18n="Blast Message">Report</div>
                        </a>
                    </li>
                    <li class="menu-item {{ request()->is('user*') ? 'active' : '' }}">
                        <a href="{{ route('users.index') }}" class="menu-link">
                            <i class='menu-icon notepad bx bx-user'></i>
                            <div data-i18n="Inbox">User</div>
                        </a>
                    </li>

                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->

                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu bx-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

                        <marquee behavior="scroll" direction="left" scrollamount="3" scrolldelay="5" onmouseover="this.stop()" onmouseout="this.start()">
                            Kini Abitour Travel Haji dan Umroh telah menggunakan payment gateway yang lebih aman dan nyaman. <a href="https://www.abitour.com/payment" target="_blank">Klik disini</a> untuk membayar biaya perjalanan ibadah Anda.
                        </marquee>

                        <ul class="navbar-nav flex-row align-items-center ms-auto">

                            <!-- User -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar avatar-online">
                                        <img src="{{ asset('templateAdmin/assets/img/avatars/1.png') }}" alt="Default Avatar" class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">

                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold d-block">Abitour Admin</span>
                                                    <small class="text-muted">Admin</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>

                                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="bx bx-cog me-2"></i> Change Password</a></li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                    </li>
                                    <li><a class="dropdown-item" href="{{ route('logout') }}"><i class="bx bx-power-off me-2"></i>
                                            Log Out</a></li>
                                </ul>
                            </li>

                            <!--/ User -->
                        </ul>

                    </div>
                </nav>

                <!-- / Navbar -->
                <!-- Change Password Modal -->
                <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="changePasswordForm" method="POST" action="{{ route('password.update') }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="currentPassword" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirmPassword" name="new_password_confirmation" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Update Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    @yield('dashboard')
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row">
                            <div class="col-xxl">
                                @yield('content')
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                ©
                                <script>
                                    document.write(new Date().getFullYear());
                                </script>
                                , made with by
                                <a href="#" target="_blank" class="footer-link fw-bolder">CloudSide
                                    Official</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{ asset('templateAdmin') }}/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="{{ asset('templateAdmin') }}/assets/vendor/libs/popper/popper.js"></script>
    <script src="{{ asset('templateAdmin') }}/assets/vendor/js/bootstrap.js"></script>
    <script src="{{ asset('templateAdmin') }}/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="{{ asset('templateAdmin') }}/assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="{{ asset('templateAdmin') }}/assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="{{ asset('templateAdmin') }}/assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="{{ asset('templateAdmin') }}/assets/js/dashboards-analytics.js"></script>
    <script src="{{ asset('templateAdmin') }}/assets/js/ui-toasts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Datepicker --}}
    <script src="{{ asset('air-datepicker') }}/datepicker.js"></script>
    <script src="/air-datepicker/air-datepicker.js"></script>
    <script src="{{ asset('templateAdmin') }}/air-datepicker\dist\js\datepicker.js"></script>
    <script src="{{ asset('templateAdmin') }}/air-datepicker\dist\js\i18n\datepicker.id.js"></script>
    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script src="{{ asset('templateAdmin') }}/assets/js/search.js"></script>

    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                height: 170,
                minHeight: null,
                maxHeight: null,
                focus: true,
            });
        });
    </script>
    @yield('footer')
</body>


</html>