    function sleep(ms) {
       return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * sends a request to the specified url from a form. this will change the window location.
     * @param {string} path the path to send the post request to
    */
    function post(path, params) {
      // The rest of this code assumes you are not using a library.
      // It can be made less wordy if you use one.
      var form = document.createElement("form");
      form.setAttribute("method", "post");
      form.setAttribute("action", path);

      for(var key in params) {
        if(params.hasOwnProperty(key)) {
          var hiddenField = document.createElement("input");
          hiddenField.setAttribute("type", "hidden");
          hiddenField.setAttribute("name", key);
          hiddenField.setAttribute("value", params[key]);

          form.appendChild(hiddenField);
        }
      }

      document.body.appendChild(form);
      form.submit();
    }

