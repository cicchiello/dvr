<?php
    // intentionally place this before the html tag

    setcookie('login_user', "unknown", time()-3600, '/');
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
  </style>

<script>
    "use strict";

    function sleep(ms) {
       return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function success() {
       await sleep(3000);
       open('./login.php',"_self");
    }
    
  </script>

  <body class="bg" onload="success()">

  </body>
</html>