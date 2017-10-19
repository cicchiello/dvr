<!DOCTYPE html>
<html>
  <head>
    <?php
       include('dvr_utils.php');

       echo renderLookAndFeel();
       ?>

    <link href="./login.css" media="all" rel="stylesheet">
    
  </head>
  
  <script>
    // Get the modal
    var modal = document.getElementById('id01');

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
  </script>
    
  <body class="bg" onload="document.getElementById('id01').style.display='block'">
    <!-- The Modal -->
    <div id="id01" class="modal">
      <span onclick="document.getElementById('id01').style.display='none'"
            class="close" title="Close Modal">&times;</span>

      <!-- Modal Content -->
      <form class="modal-content animate" action="./login_action.php" method="post">
        <div class="container">
          <label><b>Username</b></label>
	  <input type="text" placeholder="Enter Username" name="uname" required>

          <label><b>Password</b></label>
          <input type="password" placeholder="Enter Password" name="pswd" required>

          <button type="submit">Login</button>
	</div>

      </form>
    </div>
  </body>
</html>
