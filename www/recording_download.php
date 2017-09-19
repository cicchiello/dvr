<!DOCTYPE html>
<html>
  
  <head>
    
    <?php
       include ('dvr_utils.php');
       
       echo renderLookAndFeel();
       ?>
       
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
       await sleep(1000);
       //console.log('Two second later');
       var b = document.getElementById("download");
       b.click();
       open('./recordings.php',"_self");
    }
    
  </script>

  </head>
  
  <body class="bg" onload="init()">

    <?php
       $enabled = array(
          'live' => false,
          'recordings' => true,
          'scheduled' => false
       );
       echo renderMenu($enabled);
       ?>
    
    <div class="w3-container w3-display-middle">
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show loader">
      </div>
    </div>

    <?php
       $ini = parse_ini_file("./config.ini");
       $DbBase = $ini['couchbase'];
       $Db = "dvr";
       $detailUrl = $DbBase.'/'.$Db.'/'.$_GET['id'];
	    
       $detail = json_decode(file_get_contents($detailUrl), true);
	    
       $description = $detail['description'];
       $path = $detail['file'];
       $fileext = pathinfo($path, PATHINFO_EXTENSION);
       $readableFilename = $description.'.'.$fileext;

       //echo '<p><b style="color:red">'.$path.'</b></p>';
       //echo '<p><b style="color:red">'.$readableFilename.'</b></p>';
       echo '<a id="download" type="hidden" href="'.$path.'" download="'.$readableFilename.'"></a>';
    ?>
	  
  </body>
  
</html>
