<!DOCTYPE html>
<html>
  
  <head>
    <?php
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
       open('./profile.php',"_self");
    }
    
  </script>

  </head>
  
      <?php
	 writeName($_COOKIE['login'], $_POST['fname'], $_POST['lname']);
       ?>
	  
  <body class="bg" onload="init()">

  </body>
  
</html>
