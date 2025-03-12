            <div class="left side-menu">
                <div class="sidebar-inner slimscrollleft">

                    <!--- Sidemenu -->
                    <div id="sidebar-menu">
                        <ul>
                        	<li class="menu-title">Navigation</li>

                            <li class="has_sub">
                                <a href="dashboard.php" class="waves-effect"><i class="mdi mdi-view-dashboard"></i> <span> Dashboard </span> </a>
                         
                            </li>
<?php if($_SESSION['utype']=='1'):?>
  <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span> User Management </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                    <li><a href="add-subadmins.php">Add Sub-admin</a></li>
                                    <li><a href="manage-subadmins.php">Manage Sub-admin</a></li>
                                    <li><a href="add-users.php">Add User</a></li>
                                    <li><a href="manage-users.php">Manage User</a></li>
                                </ul>
                            </li>
<?php endif;?>
               


                            <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span>Article Categories </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                	<li><a href="add-category.php">Add Category</a></li>
                                    <li><a href="manage-categories.php">Manage Category</a></li>
                                </ul>
                            </li>

    <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span>Bulletins & Reports </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                    <li><a href="add-bulletins.php">Add Bulletins</a></li>
                                    <li><a href="manage-bulletins.php">Manage Bulletins</a></li>
                                </ul>
                            </li>               
  <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span> News articles </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                    <li><a href="add-post.php">Add news posts</a></li>
                                    <li><a href="manage-posts.php">Manage news posts</a></li>
                                </ul>
                            </li>  
                     

                            <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span> Pages </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                    <li><a href="aboutus.php">About us</a></li>
                                    <li><a href="contactus.php">Contact us</a></li>
                                </ul>
                            </li>
   <li class="has_sub">
                                <a href="javascript:void(0);" class="waves-effect"><i class="mdi mdi-format-list-bulleted"></i> <span> Comments </span> <span class="menu-arrow"></span></a>
                                <ul class="list-unstyled">
                                  <li><a href="unapprove-comment.php">Waiting for Approval </a></li>
                                    <li><a href="manage-comments.php">Approved Comments</a></li>
                                </ul>
                            </li>   

                        </ul>
                    </div>
                    <!-- Sidebar -->
                    <div class="clearfix"></div>


                </div>
                <!-- Sidebar -left -->

            </div>