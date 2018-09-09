<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">
  <link href="./menu.css" media="all" rel="stylesheet">

  <style>
  </style>

  <script>
    function init() {
       var f = document.getElementById("recordingsFrame");
       f.callback = function onChannel(url) {
          window.location.replace(url, "", "", true);
       };
    }

    async function forceLogin() {
      open('./login.php',"_self");
    }
    
  </script>
  
  </head>
  
  <?php include('dvr_utils.php'); ?>

  <body class="bg" 

    <?php
       if (isset($_COOKIE['login_user'])) {
         echo 'onload="init()">';
       } else {
         echo 'onload="forceLogin()">';
       }
       
       $enabled = array(
          'live' => false,
          'library' => true,
          'recording' => false,
          'scheduled' => false
       );
   
       echo renderMenu($enabled, $_COOKIE['login_user']);
    
       ?>
    
    <div style="height:90%; width:50%; padding:20px"
	 class="w3-white w3-round-large w3-panel w3-display-bottomright">

      <iframe id="recordingsFrame" src="./recordingTbl.php"
	      height="100%" width="100%" frameborder="1" style="float:right; z-index:999">
	<p>Your browser does not support iframes.</p>
      </iframe>

    </div>

</body>
</html>
