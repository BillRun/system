app.directive('disableArrows', function() {

  function disableArrows(event) {
		event.preventDefault();
  }

  return {
    link: function(scope, element, attrs) {
      element.on('mousewheel', disableArrows);
    }
  };  
});