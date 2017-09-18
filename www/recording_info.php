<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">
  <link href="./menu2.css" media="all" rel="stylesheet">

  <style>
     .popupBtn:hover {
        outline: none; /* Remove outline */
        cursor: pointer; /* Add a mouse pointer on hover */
     }
  </style>

  <script>
    function reload() {
       window.location.replace('./recordings.php', "", "", true);
    }
    
    function init() {
       var f = document.getElementById("recordingsFrame");
       f.callback = function onChannel(url) {
          window.location.replace(url, "", "", true);
       };
    }

  </script>
  
  </head>
  
  <body class="bg" onload="init()">

    <?php
       include ('dvr_utils.php');
       
       echo renderMenu();
       ?>

    <div id="detail"
	 class="w3-container w3-display-middle w3-panel w3-card w3-white w3-padding-16 w3-round-large">
	<?php echo renderEntryInfo($_GET['id']); ?>
	
	<br>
	<div class="popupBtn">
	   <img id="return" onclick="reload()" src="img/return.png"
	        align="left" width="64" height="64" title="Return">
	</div>
    </div>

</body>
</html>
