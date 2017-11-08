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
         await sleep(2000);
         //console.log('Two second later');
         open('./recordings.php',"_self");
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
	
       $id = $_GET["id"];
	   
       $ini = parse_ini_file("./config.ini");
       $DbBase = $ini['couchbase'];
       $Db = "dvr";
       $WriteDb = $DbBase.'/'.$Db;
       $couchUrl = $WriteDb.'/'.$id;

       $detail = json_decode(file_get_contents($couchUrl), true);
       unset($detail['_id']);
       $detail['type'] = 'deleted-'.$detail['type'];
       $detail['delete-timestamp'] = date_timestamp_get(date_create());
	
       $dataStr = json_encode($detail);

       $ch = curl_init($couchUrl);
       curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
       curl_setopt($ch, CURLOPT_POSTFIELDS, $dataStr);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
       curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	  'Content-Type: application/json;charset=UTF-8',
	  'Content-Length: '.strlen($dataStr))
       );
       $resultStr = curl_exec($ch);
       $result = json_decode($resultStr, true);

       if ($result['ok']) {
	  echo "Success";
       } else {
	  echo "Error!";
       }
	
    ?>
	
    <div class="w3-container w3-display-middle">
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show loader">
      </div>
    </div>

  </body>
  
</html>
