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
    function init() {
       var f = document.getElementById("recordingsFrame");
       f.callback = function onChannel(url) {
          window.location.replace(url, "", "", true);
       };
    }

  </script>
  
  </head>
  
  <?php include('dvr_utils.php'); ?>

  <body class="bg" onload="init()">

    <?php echo renderMenu(); ?>
    
    <div style="height:90%; width:50%; padding:20px; float:right"
	 class="w3-white w3-round-large w3-panel">

      <iframe id="recordingsFrame" src="./recordingTbl.php"
	      height="100%" width="100%" frameborder="1" style="float:right; z-index:999">
	<p>Your browser does not support iframes.</p>
      </iframe>

    </div>

</body>
</html>
