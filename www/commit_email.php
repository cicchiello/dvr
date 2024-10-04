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
       await sleep(1000);
       open('./index.php',"_self");
    }
    
  </script>

  </head>
  
      <?php
	 writeEmail($_POST['id'], $_POST['email']);
       ?>
	  
  <body class="bg" onload="init()">

  </body>
  
</html>
