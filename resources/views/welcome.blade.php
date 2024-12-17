<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sales Dashboard</title>
    <meta name="Description" content="Sales Dashboard for monitoring sales data and performance">
    <meta name="Author" content="Your Company">
    <meta name="keywords" content="sales dashboard, performance tracking, sales analytics, admin dashboard">

    <!-- Favicon -->
    <link rel="icon" href="/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script src="/js/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="/css/styles.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="/css/icons.css" rel="stylesheet">

</head>

<body class="authenticationcover-background bg-primary-transparent position-relative" id="particles-js">

    <!-- Sidebar Navigation -->


    <!-- Dashboard Content -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sales Overview -->


            <!-- Recent Sales -->
            <div class="col-xl-3">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <div class="d-flex align-items-start flex-wrap gap-3 p-3 mb-1">
                            <div>
                                <span class="avatar avatar-md bg-primary svg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256">
                                        <path d="M244.8,150.4a8,8,0,0,1-11.2-1.6A51.6,51.6,0,0,0,192,128a8,8,0,0,1-7.37-4.89,8,8,0,0,1,0-6.22A8,8,0,0,1,192,112a24,24,0,1,0-23.24-30,8,8,0,1,1-15.5-4A40,40,0,1,1,219,117.51a67.94,67.94,0,0,1,27.43,21.68A8,8,0,0,1,244.8,150.4ZM190.92,212a8,8,0,1,1-13.84,8,57,57,0,0,0-98.16,0,8,8,0,1,1-13.84-8,72.06,72.06,0,0,1,33.74-29.92,48,48,0,1,1,58.36,0A72.06,72.06,0,0,1,190.92,212ZM128,176a32,32,0,1,0-32-32A32,32,0,0,0,128,176ZM72,120a8,8,0,0,0-8-8A24,24,0,1,1,87.24,82a8,8,0,1,0,15.5-4A40,40,0,1,0,37,117.51,67.94,67.94,0,0,0,9.6,139.19a8,8,0,1,0,12.8,9.61A51.6,51.6,0,0,1,64,128,8,8,0,0,0,72,120Z"></path>
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-fill">
                                <span class="d-block mb-1">Total Customers</span>
                                <h3 class="fw-semibold mb-0 lh-1">2,54,244</h3>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-transparent"><i class="ti ti-arrow-narrow-up me-1"></i>0.16%</span>
                            </div>
                        </div>
                        <div id="total-customers"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <div class="d-flex align-items-start flex-wrap gap-3 p-3 mb-1">
                            <div>
                                <span class="avatar avatar-md bg-primary svg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256">
                                        <path d="M244.8,150.4a8,8,0,0,1-11.2-1.6A51.6,51.6,0,0,0,192,128a8,8,0,0,1-7.37-4.89,8,8,0,0,1,0-6.22A8,8,0,0,1,192,112a24,24,0,1,0-23.24-30,8,8,0,1,1-15.5-4A40,40,0,1,1,219,117.51a67.94,67.94,0,0,1,27.43,21.68A8,8,0,0,1,244.8,150.4ZM190.92,212a8,8,0,1,1-13.84,8,57,57,0,0,0-98.16,0,8,8,0,1,1-13.84-8,72.06,72.06,0,0,1,33.74-29.92,48,48,0,1,1,58.36,0A72.06,72.06,0,0,1,190.92,212ZM128,176a32,32,0,1,0-32-32A32,32,0,0,0,128,176ZM72,120a8,8,0,0,0-8-8A24,24,0,1,1,87.24,82a8,8,0,1,0,15.5-4A40,40,0,1,0,37,117.51,67.94,67.94,0,0,0,9.6,139.19a8,8,0,1,0,12.8,9.61A51.6,51.6,0,0,1,64,128,8,8,0,0,0,72,120Z"></path>
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-fill">
                                <span class="d-block mb-1">Total Customers</span>
                                <h3 class="fw-semibold mb-0 lh-1">2,54,244</h3>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-transparent"><i class="ti ti-arrow-narrow-up me-1"></i>0.16%</span>
                            </div>
                        </div>
                        <div id="total-customers"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card custom-card overflow-hidden">
                    <div class="card-body p-0">
                        <div class="d-flex align-items-start flex-wrap gap-3 p-3 mb-1">
                            <div>
                                <span class="avatar avatar-md bg-primary svg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#000000" viewBox="0 0 256 256">
                                        <path d="M244.8,150.4a8,8,0,0,1-11.2-1.6A51.6,51.6,0,0,0,192,128a8,8,0,0,1-7.37-4.89,8,8,0,0,1,0-6.22A8,8,0,0,1,192,112a24,24,0,1,0-23.24-30,8,8,0,1,1-15.5-4A40,40,0,1,1,219,117.51a67.94,67.94,0,0,1,27.43,21.68A8,8,0,0,1,244.8,150.4ZM190.92,212a8,8,0,1,1-13.84,8,57,57,0,0,0-98.16,0,8,8,0,1,1-13.84-8,72.06,72.06,0,0,1,33.74-29.92,48,48,0,1,1,58.36,0A72.06,72.06,0,0,1,190.92,212ZM128,176a32,32,0,1,0-32-32A32,32,0,0,0,128,176ZM72,120a8,8,0,0,0-8-8A24,24,0,1,1,87.24,82a8,8,0,1,0,15.5-4A40,40,0,1,0,37,117.51,67.94,67.94,0,0,0,9.6,139.19a8,8,0,1,0,12.8,9.61A51.6,51.6,0,0,1,64,128,8,8,0,0,0,72,120Z"></path>
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-fill">
                                <span class="d-block mb-1">Total Customers</span>
                                <h3 class="fw-semibold mb-0 lh-1">2,54,244</h3>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-transparent"><i class="ti ti-arrow-narrow-up me-1"></i>0.16%</span>
                            </div>
                        </div>
                        <div id="total-customers"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Analytics -->
        <div class="col-xl-8">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        Session Duration By Users
                    </div>
                </div>
                <div class="card-body">
                    <div id="session-users"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Particles JS -->
    <script src="/libs/particles.js/particles.js"></script>

    <script src="/js/cover-password.js"></script>

    <!-- Show Password JS -->
    <script src="/js/show-password.js"></script>
</body>

</html>