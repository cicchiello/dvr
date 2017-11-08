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
       $enabled = array(
          'live' => false,
          'library' => true,
          'recording' => false,
          'scheduled' => false
       );
       echo renderMenu($enabled);
       ?>

    <div id="detail"
	 class="w3-container w3-display-middle w3-panel w3-card w3-white w3-padding-16 w3-round-large">
      <form id="delRecording" action="./commit_del.php" method="GET">

	<fieldset>
	  <legend>Really Delete?</legend>
	  <?php echo renderEntryInfo($_GET['id']); ?>
	</fieldset>
	  
	<input id="id" type="hidden" name="id" value=<?php echo '"'.$_GET['id'].'"';?> >
	<br>
	<img id="cancelSchedule" onclick="reload()" src="img/cancel.png"
	     width="64" height="64" title="Cancel" class="popupBtn">
	<input id="commitDelete" type="image" src="img/ok.png" alt="Submit" title="Submit"
	       align="right" width="64" height="64" class="popupBtn">
      </form>
    </div>

</body>
</html>
