jQuery(document).ready(function($) {

    $('#realty_form').submit(function(event){
        // cancels the form submission
        event.preventDefault();
        realty_add_post();
    });

    function realty_add_post() {

        var fd = new FormData($('#realty_form')[0]);
        fd.append( "image", $('#r_image')[0].files[0]);
        fd.append( "action", 'realty_add_post');


        $.ajax({

            method: 'POST',
            type: 'POST',
            cache : false,
            processData: false,
            contentType: false,

            url: realty_ajax.ajaxurl,
            data: fd,

            success: function(data, textStatus, XMLHttpRequest) {
                var id = '#realty_response';
                $(id).html('');
                $(id).append(data);
                $('#realty_response').show();

                $('#realty_form').trigger('reset');
            },

            error: function(MLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }

        });
    }

});

