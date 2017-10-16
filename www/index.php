<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">
  <link href="./menu2.css" media="all" rel="stylesheet">

  <style>
  </style>

  <script>
    function menuAction() {
      var x = document.getElementById("menuItems");
      if (x.className.indexOf("w3-show") == -1) {
         x.className += " w3-show";
      } else {
         x.className = x.className.replace(" w3-show", "");
      }
    }
  </script>
  
  </head>
  
  <body class="bg">

    <?php       
       include('dvr_utils.php');
       echo renderMainMenu();
       ?>

    <div class="row box col-sm-4 w3-panel w3-card w3-white w3-round-large w3-display-bottomright">
      <?php
	 $url = "http://ipv4-api.hdhomerun.com/discover";
	 $devices = json_decode(file_get_contents($url), true);
	 
	 $cnt = count($devices);
	 if ($cnt == 0) {
	    echo '    <p>No HDHomeRun detected.</p>';
	    echo '    <p>Please connect the HDHomeRun to your router and refresh the page.</p>';
	    echo '    <p>HDHomeRun PRIME: Please remove the CableCard to allow detection to complete.</p>';
	 }
	 ?>
    </div>
    
  </body>
</html>
