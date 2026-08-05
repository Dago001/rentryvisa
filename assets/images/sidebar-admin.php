<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="keywords" content="immigration, admin dashboard,enrolment, ID card" />
    <meta name="description" content="NISREV is an in-house solution built and deployed by the Web Team of the Nigeria Immigration Service led by ACI P Odeh. Other members of the team includes JT Vihishima, G Almustapha, Dagogo Gift, Adanma Eze, Taofeeq Lasisi, Usman Baffa" />
    <link rel="icon" href="images/favicon.ico" type="image/ico" />
    <title>NIS Re-Entry Visa System</title>

    <!-- Bootstrap -->
    <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
    <!-- NProgress -->
    <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
    <!-- iCheck -->
    <link href="vendors/iCheck/skins/flat/green.css" rel="stylesheet">
    <!-- bootstrap-progressbar -->
    <link href="vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css" rel="stylesheet">
    <!-- JQVMap -->
    <link href="vendors/jqvmap/dist/jqvmap.min.css" rel="stylesheet"/>
    <!-- bootstrap-daterangepicker -->
    <link href="vendors/bootstrap-daterangepicker/daterangepicker.css" rel="stylesheet">
    <!-- Custom Theme Style -->
    <link href="build/css/custom.css" rel="stylesheet">
    <script src="js/lga.js"></script>

    <style>
        /* Reset and base styles */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        /* Full height sidebar container */
        .container.body {
            height: 100%;
            min-height: 100vh;
        }
        
        .main_container {
            display: flex;
            min-height: 100vh;
            height: auto;
        }
        
        /* Left sidebar - Full height with background */
        .left_col {
            background: #2A3F54 !important;
            width: 230px;
            min-height: 100vh;
            height: 100%;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        
        .left_col.scroll-view {
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            background: #2A3F54;
        }
        
        /* Right content area */
        .right_col {
            margin-left: 230px;
            background: #f8f9fa;
            min-height: 100vh;
            width: calc(100% - 230px);
            padding-bottom: 50px;
        }
        
        /* Sidebar specific styles */
        .nav_title {
            background: #172D44;
            border-radius: 0;
            height: 57px;
            padding: 15px;
            position: relative;
            width: 100%;
            z-index: 1001;
        }
        
        .site_title {
            color: #ECF0F1 !important;
            font-size: 22px;
            font-weight: 400;
        }
        
        .site_title img {
            margin-right: 10px;
        }
        
        .profile {
            padding: 15px;
            background: rgba(255,255,255,0.1);
            margin: 10px;
            border-radius: 5px;
        }
        
        .profile_img {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .profile_info {
            padding: 0 10px;
            color: #ECF0F1;
        }
        
        .profile_info span {
            font-size: 13px;
            color: #B4BCC2;
        }
        
        .profile_info h2 {
            font-size: 14px;
            color: #ECF0F1;
            margin: 0;
        }
        
        #sidebar-menu {
            margin-top: 20px;
            padding-bottom: 50px;
        }
        
        .menu_section {
            margin-bottom: 35px;
        }
        
        .menu_section h3 {
            padding-left: 23px;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: .5px;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 0;
            margin-top: 0;
            text-shadow: 1px 1px 1px #000;
        }
        
        .menu_section > ul {
            margin-top: 10px;
        }
        
        .side-menu {
            margin-top: 10px;
            padding: 0;
            list-style: none;
        }
        
        .side-menu li {
            position: relative;
        }
        
        .side-menu li a {
            color: #E7E7E7;
            font-weight: 500;
            padding: 13px 15px;
            display: block;
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s;
        }
        
        .side-menu li a:hover,
        .side-menu li a:focus {
            background: rgba(255,255,255,0.1);
            color: #FFF;
        }
        
        .side-menu li.active > a {
            background: rgba(255,255,255,0.1);
            color: #FFF;
            border-left: 4px solid #1ABB9C;
        }
        
        .side-menu > li > a {
            border-left: 4px solid transparent;
        }
        
        .child_menu {
            background: rgba(0,0,0,0.2);
            display: none;
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .child_menu li a {
            padding: 10px 15px 10px 40px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        
        .child_menu li a:hover {
            background: rgba(255,255,255,0.05);
        }
        
        .fa {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        
        /* Ensure the entire sidebar has background */
        .col-md-3.left_col {
            background: #2A3F54;
            padding: 0;
        }
        
        /* Scrollbar styling for sidebar */
        .left_col::-webkit-scrollbar {
            width: 6px;
        }
        
        .left_col::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .left_col::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .left_col::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .left_col {
                position: relative;
                width: 100%;
                min-height: auto;
                height: auto;
            }
            
            .right_col {
                margin-left: 0;
                width: 100%;
            }
            
            .main_container {
                flex-direction: column;
            }
            
            .nav_title {
                position: relative;
            }
        }

        /* Force full height for all parent elements */
        body.nav-md {
            background: #2A3F54;
        }
        
        body.nav-md .container.body {
            background: #2A3F54;
        }
        
        body.nav-md .container.body .left_col {
            background: #2A3F54;
        }
    </style>
  </head>
 
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <!-- Sidebar -->
        <div class="col-md-3 left_col">
          <div class="left_col scroll-view">
            <div class="navbar nav_title" style="border: 0;">
              <a href="mainpage.php" class="site_title">
                <img
                  src="assets/images/logo.png"
                  alt="homepage"
                  class="light-logo"
                  width="30"
                />  
                <span>NISREV</span>
              </a>
            </div>

            <div class="clearfix"></div>

            <!-- menu profile quick info -->
            <div class="profile clearfix">
              <div class="profile_pic">
                <img src="images/<?php echo $_SESSION['user_id']; ?>.jpg" alt="..." class="img-circle profile_img" 
                     onerror="this.src='images/default-user.jpg'">
              </div>
              <div class="profile_info">
                <span>Welcome,</span>
                <h2><?php echo htmlspecialchars($_SESSION['username']); ?></h2>
              </div>
            </div>
            <!-- /menu profile quick info -->

            <br />

            <!-- sidebar menu -->
            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
              <div class="menu_section">
                <h3>Admin Menu</h3>
                <ul class="nav side-menu">
                  <li>
                    <a><i class="fa fa-home"></i> Home <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="mainpage.php">Dashboard</a></li>
                    </ul>
                  </li>
                  <li>
                    <a><i class="fa fa-database"></i> Visa Management <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="new_record.php">New Record</a></li>
                      <li><a href="rentry_visas.php">View Issued Visas</a></li>
                      <li><a href="print-visa.php">Print Re-Entry Visa</a></li>
                    </ul>
                  </li>
                  <li>
                    <a><i class="fa fa-book"></i> Update Visa Records <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="update_rank.php">Update Applicant Info</a></li>
                      <li><a href="enrolment-update.php">Update Re-Entry Visa Info</a></li>
                    </ul>
                  </li>
                  <li>
                    <a><i class="fa fa-user"></i> User Management <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="createuser.php">Create User</a></li>
                      <li><a href="edit-user.php">Edit User</a></li>
                    </ul>
                  </li>
                  <li>
                    <a><i class="fa fa-bar-chart-o"></i> Reports <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="nominal_roll.php">List of Re-Entry Visas</a></li>
                      <li><a href="employeeChart.php">List of Applicants by Country</a></li>
                    </ul>
                  </li>
                </ul>
              </div>
            </div>
            <!-- /sidebar menu -->
          </div>
        </div>

        <!-- Top Navigation -->
        <?php include 'top-navigation.php'; ?>

        <!-- Page Content - This will contain your new_record.php form -->
        <div class="right_col" role="main">
          <!-- Your new_record.php form content will be included here -->
          <?php 
          // The form content from new_record.php will be rendered here
          // This ensures the sidebar extends to match the form height
          ?>
        </div>

        <!-- Footer -->
        <?php include 'footer-enrol.php'; ?>
      </div>
    </div>

    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle sidebar dropdowns
            const menuItems = document.querySelectorAll('.side-menu > li > a');
            
            menuItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (this.parentElement.querySelector('.child_menu')) {
                        e.preventDefault();
                        
                        // Close other open menus
                        menuItems.forEach(otherItem => {
                            if (otherItem !== this) {
                                otherItem.parentElement.classList.remove('active');
                                const otherChild = otherItem.nextElementSibling;
                                if (otherChild && otherChild.classList.contains('child_menu')) {
                                    otherChild.style.display = 'none';
                                }
                            }
                        });
                        
                        // Toggle current menu
                        this.parentElement.classList.toggle('active');
                        const childMenu = this.nextElementSibling;
                        if (childMenu && childMenu.classList.contains('child_menu')) {
                            if (childMenu.style.display === 'block') {
                                childMenu.style.display = 'none';
                            } else {
                                childMenu.style.display = 'block';
                            }
                        }
                    }
                });
            });

            // Auto-expand current page menu
            const currentPage = window.location.pathname.split('/').pop();
            const menuLinks = document.querySelectorAll('.side-menu a[href="' + currentPage + '"]');
            
            menuLinks.forEach(link => {
                let parent = link.parentElement;
                while (parent && !parent.classList.contains('side-menu')) {
                    if (parent.classList.contains('child_menu')) {
                        parent.style.display = 'block';
                        const parentLink = parent.previousElementSibling;
                        if (parentLink) {
                            parentLink.parentElement.classList.add('active');
                        }
                    }
                    parent = parent.parentElement;
                }
            });

            // Force sidebar to full height
            function adjustSidebarHeight() {
                const sidebar = document.querySelector('.left_col');
                const content = document.querySelector('.right_col');
                
                if (sidebar && content) {
                    const contentHeight = content.scrollHeight;
                    const windowHeight = window.innerHeight;
                    const maxHeight = Math.max(contentHeight, windowHeight);
                    
                    sidebar.style.height = maxHeight + 'px';
                    document.querySelector('.left_col.scroll-view').style.height = maxHeight + 'px';
                }
            }

            // Adjust height on load and resize
            adjustSidebarHeight();
            window.addEventListener('resize', adjustSidebarHeight);
            window.addEventListener('load', adjustSidebarHeight);
            
            // Also adjust after a short delay to account for dynamic content
            setTimeout(adjustSidebarHeight, 100);
        });
    </script>
  </body>
</html>