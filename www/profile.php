<!DOCTYPE html>
<html>
  <head>
    <?php
       include('dvr_utils.php');

       echo renderLookAndFeel();
       
       ?>

    <link href="./table.css" media="all" rel="stylesheet">
    <link href="./thumbs.css" media="all" rel="stylesheet">
    
    <script src="./dvr_utils.js"></script>
  
  </head>
  
  <script>
    async function onEditUsername(uname) {
      post("./username_action.php", {"uname":uname});
    }

    async function onEditEmail(email) {
      post("./email_action.php", {"email":email});
    }

    async function onEditPassword(fname,lname) {
      open("./password_action.php?fname="+fname+"&lname="+lname, "_self");
    }

    async function forceLogin() {
      open("./login.php", "_self");
    }

    async function onEditName(fname,lname) {
      post("./name_action.php", {
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

	 $usersUrl = $DbBase.'/'.$Db.'/'.$_COOKIE['login'];
         $row = json_decode(file_get_contents($usersUrl), true);
	 $fname = $row['firstname'];
	 $lname = $row['lastname'];
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
            <th rowspan="1" style="text-align:left">Name:</th>
	    <?php
		echo '<td>'.$fname.' '.$lname.'</td>';
	     ?>
	    <td>
	      <div class="thumbs">
	        <span class="columns-1-wide">
		  <?php
		    $cmd = '<img onclick="onEditName('."'".$fname."','".$lname."'";
		    $cmd .= ')" src="img/edit2.png" class="Btn" title="Edit">';
		    echo $cmd;
		    ?>
		</span>
	      </div>
	    </td>
	  </tr>
          <tr style = "background-color: #e2f4dd">
            <th rowspan="1" style="text-align:left">Username:</th>
	    <?php
		echo '<td>'.$username.'</td>';
	     ?>
	    <td>
	      <div class="thumbs">
	        <span class="columns-1-wide">
		  <?php
		    $cmd = '<img onclick="onEditUsername('."'".$username."'";
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
		    $cmd = '<img onclick="onEditEmail('."'".$email."'";
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
		    $cmd = '<img onclick="onEditPassword('."'".$fname."','".$lname."'";
		    $cmd .= ')" src="img/edit2.png" class="Btn" title="Edit">';
		    echo $cmd;
		    ?>
		</span>
	      </div>
	    </td>
	  </tr>
        </table>
	  <br>
	  <img onclick="forceLogin()" src="img/ok.png" width="48" height="48"
	       title="Done" class="popupBtn" align="right">
      </div>
    </div>
  </body>
</html>
