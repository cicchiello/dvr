<!DOCTYPE html>
<html>
  <head>
    <?php
       // Uncomment to see php errors
       //ini_set('display_errors', 1);
       //ini_set('display_startup_errors', 1);
       //error_reporting(E_ALL);
    
       include('dvr_utils.php');

       echo renderLookAndFeel();
      ?>

    <link href="./table.css" media="all" rel="stylesheet">
    <link href="./thumbs.css" media="all" rel="stylesheet">
    
    <script src="./dvr_utils.js"></script>
  
  </head>
  
  <script>
    async function onEditEmail(id,email) {
      post("./email_action.php", {"id":id,"email":email});
    }

    async function onEditPassword(id) {
      post("./password_action.php", {"id":id});
    }

    async function forceLogin() {
      open("./login.php", "_self");
    }

    async function cancelEdit() {
      open("./index.php", "_self");
    }

    async function onEditName(id,fname,lname) {
      post("./name_action.php", {
        "id":id,
        "fname":fname,
        "lname":lname
      });
    }

  </script>

  <body class="bg" 

    <?php
       if (isset($_COOKIE['login_user'])) {
         echo '> ';
    
         $ini = parse_ini_file("./config.ini");
         $DbBase = $ini['couchbase'];
         $Db = $ini['dbname'];

	 $usersUrl = $DbBase.'/'.$Db.'/_design/'.$Db.'/_view/user?key="'.$_COOKIE['login_user'].'"';
         $row = json_decode(file_get_contents($usersUrl), true)['rows'][0]['value'];
         $id = $row['_id'];
	 $fname = $row['fname'];
	 $lname = $row['lname'];
	 $username = $row['username'];
         $email = $row['email'];
       } else {
         echo 'onload="forceLogin()"> ';
       }
       
       ?>

    <div class="w3-container w3-display-middle w3-show">
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large">
        <table style="width:80%">
          <tr style = "background-color: #e2f4dd">
            <th rowspan="1" style="text-align:left">Username:</th>
	    <?php
		echo '<td>'.$username.'</td>';
	     ?>
	    <td>
	    </td>
	  </tr>
          <tr style = "background-color: #e2f4dd">
            <th rowspan="1" style="text-align:left">Name:</th>
	    <?php
		echo '<td>'.$fname.' '.$lname.'</td>';
	     ?>
	    <td>
	      <div class="thumbs">
	        <span class="columns-1-wide">
		  <?php
		    $cmd = '<img onclick="onEditName('."'".$id."','".$fname."','".$lname."'";
		    $cmd .= ')" src="img/edit2.png" class="Btn" title="Edit">';
		    echo $cmd;
		    ?>
		</span>
	      </div>
	    </td>
	  </tr>
          <tr style = "background-color: #e2f4dd">
            <th rowspan="1" style="text-align:left">email:</th>
	    <?php
		echo '<td>'.$email.'</td>';
	     ?>
	    <td>
	      <div class="thumbs">
	        <span class="columns-1-wide">
		  <?php
		    $cmd = '<img onclick="onEditEmail('."'".$id."','".$email."'";
		    $cmd .= ')" src="img/edit2.png" class="Btn" title="Edit">';
		    echo $cmd;
		    ?>
		</span>
	      </div>
	    </td>
	  </tr>
          <tr style = "background-color: #e2f4dd">
            <th rowspan="1" style="text-align:left">password:</th>
	    <td>******************</td>
	    <td>
	      <div class="thumbs">
	        <span class="columns-1-wide">
		  <?php
		    $cmd = '<img onclick="onEditPassword('."'".$id."'";
		    $cmd .= ')" src="img/edit2.png" class="Btn" title="Edit">';
		    echo $cmd;
		    ?>
		</span>
	      </div>
	    </td>
	  </tr>
        </table>
	<br>
	<img onclick="cancelEdit()" src="img/cancel.png" width="48" height="48"
	     title="Cancel" class="popupBtn" align="left"/>
      </div>
    </div>
  </body>
</html>
