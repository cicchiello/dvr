<?php
    // intentionally place this before the html tag
    unset($_COOKIE['login']);
  
    $ini = parse_ini_file("./config.ini");
    $DbBase = $ini['couchbase'];
    $Db = "dvr";
    $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
    $WriteDb = $DbBase.'/'.$Db;

    $trace = 1;

    $success = 0;
    $usersUrl = $DbViewBase.'/user?key="'.$_POST['uname'].'"';
    $user_detail = json_decode(file_get_contents($usersUrl), true);
    $row = $user_detail['rows'][0]['value'];
    if ($row['password'] == $_POST['pswd']) {
       setcookie('login', $row['_rev'], time()+24*60*60*1000, '/');

       $couchUrl = $WriteDb.'/'.$row['_id'];
       unset($row['_id']);
       $row['last-login'] = time();
       $ch = curl_init($couchUrl);
       $dataStr = json_encode($row);
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
       curl_setopt($ch, CURLOPT_POSTFIELDS, $dataStr);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_HTTPHEADER, array(
       	      'Content-Type: application/json;charset=UTF-8',
	      'Content-Length: '.strlen($dataStr))
       );
       $resultStr = curl_exec($ch);
       $result = json_decode($resultStr, true);
       $trace = 2;

       $success = 1;
    }
  ?>


<html>

  <head>
    <?php
       include('dvr_utils.php');

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
       //await sleep(8000);
       open('./index.php',"_self");
    }
    
    async function failed() {
       document.getElementById('errorSplash').style.display='block';
       await sleep(8000);
       open('./login.php',"_self");
    }
    
  </script>

  <body class="bg"

    <?php
      if ($success == 1) {
        echo 'onload="success()">';
      } else {
        echo 'onload="failed()">';
	echo "Failed!";
      }
      ?>

    <div id="errorSplash" class="modal">
      <!-- Modal Content -->
        <div class="animate modal-content err">
	   <div class="container">
	      <b>Invalid Username or Password</b>
	   </div>
	</div>
    </div>
    
  </body>
</html>