jQuery(document).ready( function($) {
    $('.edit-republish').click( function() {
        if ( $('#gtuk-republish').is(':hidden') ) {
            $('#gtuk-republish').slideDown('fast');
            $('.edit-republish').hide();
        }
    });

    $('.gtuk-cancel-republish').click( function() {
        $('#gtuk-republish').slideUp('fast');
        $('.edit-republish').show();
    });
});