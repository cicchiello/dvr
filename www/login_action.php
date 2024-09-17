<!DOCTYPE html>

<?php
    // intentionally place this before the html tag

    // Uncomment to see php errors
    //ini_set('display_errors', 1);
    //ini_set('display_startup_errors', 1);
    //error_reporting(E_ALL);

    include('dvr_utils.php');

    $postError = 1;
    $success = 0;
    
    setcookie('login_user', "unknown", time()-3600, '/');
    if (isset($_POST['uname']) && isset($_POST['pswd']))
    {
      $postError = 0;
      $ini = parse_ini_file("./config.ini");
      $DbBase = $ini['couchbase'];
      $sessionTimeout_s = $ini['sessionTimeout_s'];
      $Db = "dvr";
      $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';

      $usersUrl = $DbViewBase.'/user?key="'.$_POST['uname'].'"';
      $user_detail = json_decode(file_get_contents($usersUrl), true);
      $success = 0;
      if (count($user_detail['rows']) > 0)
      {
         $row = $user_detail['rows'][0]['value'];
         $pswd = hash('sha256', $_POST['pswd']);
         $pswd2 = $row['password'];
         if ($pswd2 == $pswd) {
            $uid = $row['_id'];
            unset($row['_id']);

            // init cookie with timeout
            setcookie("login_user", $_POST['uname'], time()+$sessionTimeout_s, '/');

            $success = 1;
         }
      }
    }
  ?>


<html>

  <head>
    <?php
       echo renderLookAndFeel();
     ?>

    <link href="./login.css" media="all" rel="stylesheet">
    
  </head>

  <style>
    .err {
        background-color: #fe4040;
	border: 3px solid #73AD21;
	text-align: center;
	width: 50%; /* Could be more or less, depending on screen size */
    }
  </style>

  <script>
    "use strict";

    function sleep(ms) {
       return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function success() {
       await sleep(500);
       open('./index.php',"_self");
    }
    
    async function loginFailed() {
       document.getElementById('invalidUserSplash').style.display='block';
       await sleep(5000);
       open('./login.php',"_self");
    }
    
    async function loginError() {
       document.getElementById('errorSplash').style.display='block';
       await sleep(5000);
       open('./login.php',"_self");
    }
    
  </script>

  <body class="bg"

    <?php
      if ($postError == 0) {
	if ($success == 1) {
          echo 'onload="success()">';
        } else {
          echo 'onload="loginFailed()">';
        }
      } else {
	echo 'onload="loginError()">';
      }
      //echo var_dump($_REQUEST);
      //echo var_dump($_COOKIE['login_user']);
     ?>

    <div id="invalidUserSplash" class="modal">
      <!-- Modal Content -->
        <div class="animate modal-content err">
	   <div class="container">
	      <b>Invalid Username or Password</b>
	   </div>
	</div>
    </div>
    
    <div id="errorSplash" class="modal">
      <!-- Modal Content -->
        <div class="animate modal-content err">
	   <div class="container">
	      <b>Unknown login error</b>
	   </div>
	</div>
    </div>
    
  </body>
</html>
