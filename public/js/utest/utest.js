//$(document).ready(function(){
//    window.countElementsIn = function(containerSel, patternSel) {
//        var parentE = jQuery(containerSel);
//        var parentCnt = parentE.length;
//        if(parentCnt > 0) {
//            return parentE.find(patternSel);
//        }
//        return 0;
//    } 
//    
//    window.genInput = function(baseName,type,val,extraClass) {
//		return '<input value="' + val + '" autocomplete="off" class="input form-control '+extraClass+'" name="'+baseName+'[]" type="'+type+'">';
//    }
//    
//    window.genBtn = function(label,extraClass) {
//		return '<span class="input-group-addon ' + extraClass + '" id="basic-addon2">' + label + '</span>';
//    }
//    
//    window.genNewRow = function(type, val, name) {
//        switch(type) {
//            default: 
//				var label = '<span class="glyphicon glyphicon-minus" aria-hidden="true"></span>';
//                return '<div class="row-fluid input-group">'+genInput(name,'text',val)+' '+genBtn(label, 'btn-danger remove-parent')+'</div>';
//        }
//        console.log('Check your code Im broken...')
//        return ''; // should never happen
//    }
//    jQuery(".add").on('click', function(e){
//        e.preventDefault(); // prevents button weird behaviour
//        var me = jQuery(this);
//        var addTo = me.parent().parent();
//        var newRec = genNewRow( me.prev().attr('type'), me.prev().val(), me.prev().attr('name') );
//        jQuery(addTo).append( newRec );
//        me.prev().val(''); // wipe our value
//        
//        jQuery('.remove-parent').click(function(){
//            e.preventDefault(); // prevents button weird behaviour
//            jQuery(this).parent().remove();
//        });
//    });
//
//    
//});
//
//
$(document).ready(function () {

	
	$('.enable-input').change(setInputByCheckboxState);
	
	function setInputByCheckboxState() {
		if ($(this).is(':checked')) {
			$(this).closest('span').next('input').prop('disabled', false);
			$(this).closest('span').next('select').prop('disabled', false);
			$(this).closest('span').next('textarea').prop('disabled', false);
			$(this).siblings('small').hide();
		} else {
			$(this).closest('span').next('input').val('');
			$(this).siblings('small').show();
			$(this).closest('span').next('input').prop('disabled', true);
			$(this).closest('span').next('select').prop('disabled', true);
			$(this).closest('span').next('textarea').prop('disabled', true);
		}
	}
	$('.enable-input').trigger('change');
});

