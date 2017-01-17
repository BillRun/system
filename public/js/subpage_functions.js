;(function($, window, document, undefined) {
	var $win = $(window);
	var $doc = $(document);

	$doc.ready(function() {
		$('.scrollable').mCustomScrollbar();

		var planContent;
		var $popupTrigger = $('<a href="#" class="popup-trigger popup-trigger-absolute"></a>');

		$('.radio-plan input').on('change', function() {
			var $this = $(this);
			var $plan = $this.closest('.radio-plan').find('.plan');

			planContent = $plan.html();
		});

		$('.form-btn-submit').on('click', function(event) {
			$('.popup').removeClass('open');

			$('.plan-current').html(planContent);
			$('.plan-current').prepend($popupTrigger);

			$('form-plans').trigger("reset");
		});
	});

	$doc.on('click', '.popup-trigger', function(event) {
		event.preventDefault();

		$('.popup').toggleClass('open');

		$('.form-plans form').trigger('reset');
	});

})(jQuery, window, document);
