<!DOCTYPE html>

<?php
    // intentionally place this before the html tag

    // Uncomment to see php errors
    //ini_set('display_errors', 1);
    //ini_set('display_startup_errors', 1);
    //error_reporting(E_ALL);

  ?>

<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
  
  <link href="./w3.css" media="all" rel="stylesheet">
  
  <link href="./style.css" media="all" rel="stylesheet" />
  <link href="./table.css" media="all" rel="stylesheet" />
  <link href="./menu.css" media="all" rel="stylesheet" />
  <link href="./loader.css" media="all" rel="stylesheet" />
  
  <style>
  </style>

  <script>
    "use strict";

    function sleep(ms) {
       return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function init() {
       //console.log('Taking a break...');
       await sleep(2000);
       //console.log('Two second later');
       open('./schedules.php',"_self");
    }
    
  </script>

  </head>
  
    <?php
       include('dvr_utils.php');
     
       $ini = parse_ini_file("./config.ini");
       $DbBase = $ini['couchbase'];
       $Db = "dvr";
       $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
       $WriteDb = $DbBase.'/'.$Db;
     ?>
	  
  <body class="bg" 
          <?php
              if (isset($_COOKIE['login_user'])) {
                echo 'onload="init()">';
              } else {
                echo 'onload="forceLogin()">';
              }
            ?>

    <?php
      $enabled = array(
	'live' => false,
	'library' => false,
	'recording' => false,
	'scheduled' => false
      );
	      
      echo renderMenu($enabled, $_COOKIE['login_user']);
    ?>
 
    <div class="w3-container w3-display-middle">
      
      <div id="scheduled" class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show">
      </div>
      
    </div>
      
    <div class="w3-container w3-display-middle">
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show loader">
      </div>
    </div>

  </body>
  
	<?php
	   $id = $_GET['id'];
           $rev = json_decode(file_get_contents($WriteDb.'/'.$id), true)['_rev'];
	   
	   $ch = curl_init($WriteDb.'/'.$id.'?rev='.$rev);
	   curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
	   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	   $resultStr = curl_exec($ch);
	   //var_dump($resultStr);

	   $result = json_decode($resultStr, true);
	   if ($result['ok']) {
	      echo "Success";
	   } else {
	      echo "Error!";
	      var_dump($resultStr);
	   }
	?>
	
</html>
