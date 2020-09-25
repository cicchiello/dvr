<!DOCTYPE html>
<html>
  
  <head>
    
    <?php
       include ('dvr_utils.php');
       
       echo renderLookAndFeel();
       ?>

   
  <style>
  </style>

  <script>
    function reload() {
       window.location.replace('./recordings.php', "", "", true);
    }
    
  </script>
  
  </head>
  
  <body class="bg">

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

    <div id="detail"
	 class="w3-container w3-display-middle w3-panel w3-card w3-white w3-padding-16 w3-round-large">
       <fieldset>
          <legend>Recording Detail:</legend>
	  <?php echo renderEntryInfo($_GET['id']); ?>
       </fieldset>
	
	<br>
	<div class="popupBtn">
	   <img id="return" onclick="reload()" src="img/return.png"
	        align="left" width="64" height="64" title="Return">
	</div>
    </div>

</body>
</html>
