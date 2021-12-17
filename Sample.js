(function ($) {
  Drupal.behaviors.websiteFeedback = {
    attach: function (context, settings) {
      if (top == self && context === document) {

        let request = new XMLHttpRequest();
        request.open("GET", "/website_feedback/json");
        request.send();
        request.onload = () => {
          console.log(request);
          if (request.status === 200) {
            // by default the response comes in the string format, we need to parse the data into JSON

            let response = JSON.parse(request.response).data;
            const currentPath = drupalSettings.path.currentPath;
            
            var popup_type = '';
            var display_option = '';
            var on_exit = false;
            const currentLanguage = drupalSettings.language;

            $('body').once('website-feedback').each(function () {
              var $link = $('<a href="/' + currentLanguage + '/admin/content/website-feedback/add" id="website-feedback-button" class="button button-website-feedback"></a>')
                .html(drupalSettings.websiteFeedback.buttonText)
                .attr('title', drupalSettings.websiteFeedback.buttonTitle);
              $('<div class="website-feedback-toggle-wrapper"></div>')
                .append($link)
                .appendTo('body')
    
              const elementSettings = {
                progress: { type: 'throbber' },
                dialogType: 'modal',
                base: $link.attr('id'),
                element: $link[0],
                url: $link.attr('href'),
                event: 'click',
                dialog: {
                  width: ($(document).width() < 600 ? '90%' : 500),
                  show: 'fadeIn',
                  title: 'Send feedback',
                  classes: {
                    "ui-dialog": "website-feedback-dialog"
                  }
                }
              };
              
              // Flag if current node doens't have popup configured and we have cookie set for on exit. 
              if (!response.hasOwnProperty('/' + currentPath)) {  
                const path = getCookie('feedback_event');
                if (path !== "" && path !== "undefined" && path !== drupalSettings.path.currentPath) {
                  on_exit = true;  
                  const res = response['/' + path]
                  popup_type = res.popup_type;
                  display_option = res.display_option;
                } 
              }
              // Set popup type and disply option with popup in configured for currrent node
              if (response.hasOwnProperty('/' + currentPath)){
                const res = response['/' + currentPath]
                popup_type = res.popup_type;
                display_option = res.display_option;
              }
              
              // Set popup as slide if based on backend config.
              if (popup_type == 'slide') {
                elementSettings.dialog.position = { my: "right bottom", at: "right-10 bottom-10" };
              }
    
              Drupal.ajax(elementSettings);
            });

            // Trigger popup for on exit cookie.
            if (on_exit) {  
              setCookie('feedback_event', "", 0);
              openPopup();
              return;
            }

            // Trigger popup for page load.
            if (display_option == 'onload') {
              openPopup();
              return;
            }

            // Trigger popup for time based popup.
            const timer = ['5sec', '20sec'];
            if (timer.includes(display_option)) {
              const delayInMilliseconds = (display_option == '5sec') ? 5000 : 20000;
              if (!$('#website-feedback-button').hasClass('processed')) {
                let timeid = setTimeout(function() {
                  openPopup();
                  $('#website-feedback-button').addClass('processed');
                }, delayInMilliseconds);
              }
            }

            // Trigger popup for scroll based popup. 
            const scroll = ['scrollmid', 'scrollend'];
            if (scroll.includes(display_option)) {
              $(document).scroll(function() {
                if (!$('#website-feedback-button').hasClass('processed')) {
                  if (display_option == 'scrollmid') {
                    if(IsScrollbarInMiddele()) {
                      openPopup();
                      $('#website-feedback-button').addClass('processed');
                    }
                  }
                  if (display_option == 'scrollend') {
                    if(IsScrollbarAtBottom()) {
                      openPopup();
                      $('#website-feedback-button').addClass('processed');
                    }
                  }
                }
              });
            }

            // Set cookie for page exit.
            if (display_option == 'exit') {
              window.onbeforeunload = function(e) {
                setCookie('feedback_event', drupalSettings.path.currentPath, 1);
              };   
            }
          } else {
            console.log(`error ${request.status} ${request.statusText}`);
          }
        };
      }

      // Trigger popup.
      function openPopup() {
        $('#website-feedback-button').click();
      }

      // Flag if user scrolled to end of the page.
      function IsScrollbarAtBottom() {
				return ($(window).scrollTop() >= (($(document).height() - $(window).height()) - 1 ));
      }

      // Flag if user scrolled to mid of the page.
      function IsScrollbarInMiddele() {
				return ($(window).scrollTop() > $(window).height() * 2);
      }

      // Set cookie.
      function setCookie(cname, cvalue, exdays) {
        const d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        let expires = "expires="+d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
      }
      
      // Getr cookie.
      function getCookie(cname) {
        let name = cname + "=";
        let ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
          let c = ca[i];
          while (c.charAt(0) == ' ') {
            c = c.substring(1);
          }
          if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
          }
        }
        return "";
      }
    }
  };
})(jQuery);
