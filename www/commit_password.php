<!DOCTYPE html>
<html>
  
  <head>
    <?php
       // Uncomment to see php errors
       //ini_set('display_errors', 1);
       //ini_set('display_startup_errors', 1);
       //error_reporting(E_ALL);
       
       include('dvr_utils.php');

       echo renderLookAndFeel();
       
       ?>

    <script src="./dvr_utils.js"></script>
  
  </head>
    
  <style>
  </style>

  <script>
    "use strict";

    async function init() {
       await sleep(500);
       open('./index.php',"_self");
    }
    
  </script>

  </head>
  
      <?php
	 writePassword($_POST['id'], $_POST['pswd']);
       ?>
	  
  <body class="bg" onload="init()">

    <?php
      //echo var_dump($_REQUEST);
      //echo var_dump($_COOKIE['login_user']);
     ?>
     
  </body>
  
</html>
