;(function($, window, document, undefined) {
	var $win = $(window);
	var $doc = $(document);

	$doc.ready(function() {
		$('.scrollable').mCustomScrollbar();

		var planContent;
		var $popupTrigger = $('<a href="#" class="popup-trigger popup-trigger-absolute"><i class="material-icons">keyboard_arrow_down</i></a>');

		$('.radio-plan input').on('change', function() {
			var $this = $(this);
			var $plan = $this.closest('.radio-plan').find('.plan');

			planContent = $plan.html();
		});
		
		$('#payment_gateway_submit').prop('disabled', true).css('background-color', 'gray');
		$('.radio-cards').on('click', function(event){
			$('#payment_gateway_submit').prop('disabled', false).css("background-color", '');;
		});	

		$('.form-btn-submit').on('click', function(event) {
			$('.popup').removeClass('open');

			$('.plan-current').html(planContent);
			$('.plan-current').prepend($popupTrigger);

			$('form-plans').trigger("reset");
		});
		$('.form-btn-reset').on('click', function(event) {
			$('.popup-trigger-close').trigger('click');
		});
	});

	$doc.on('click', '.popup-trigger', function(event) {
		event.preventDefault();

		$('.popup').toggleClass('open');

		$('.form-plans form').trigger('reset');
		var _currentPlanVal = $('.plan-current input').val();
		$('.radio-plan input[value="' + _currentPlanVal + '"]').trigger('click');
	});

})(jQuery, window, document);
