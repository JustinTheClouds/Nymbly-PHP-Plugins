(function($) {
    
    $(document).ready(function() {
        
        // Toggle feedback window
        $('.simple-feedback-button').click(function(event) {
            event.stopPropagation();
            //$(this).closest('.simple-feedback-wrapper').toggleClass('active');
            positionWindow();
        });
        
        // Dont close when content is clicked
        $('.simple-feedback-wrapper').click(function(event) {
            event.stopPropagation();
        });
        
        // Hide feedback window on document click
        $(document).click(function(event) {
            event.stopPropagation();
            $('.simple-feedback-wrapper').css('bottom', '0px');
        });
        
        function positionWindow() {
            $('.simple-feedback-wrapper').css('bottom', $('.simple-feedback-content').outerHeight() + 'px');
        }
        
        // Form submit
        $('.simple-feedback-wrapper form').submit(function(event) {
            
            event.preventDefault();
            
            var $this = $(this);
            
            // Disable button
            $this.find('input[type="submit"]').prop('disabled', true);
            $.getJSON($this.attr('action'), $this.serialize(), function(data) {
                if(typeof data['plugins/simple_feedback'] != 'undefined') {
                    $('.simple-feedback-wrapper .simple-feedback-success').hide();
                    $('.simple-feedback-wrapper .simple-feedback-error').empty().text(data['plugins/simple_feedback'].error).show();
                    $this.find('input[type="submit"]').prop('disabled', false);
                    positionWindow();
                } else {
                    $('.simple-feedback-wrapper .simple-feedback-error').hide();
                    $('.simple-feedback-wrapper .simple-feedback-success').show();
                    $('.simple-feedback-wrapper').find(':input, label').hide();
                    positionWindow();
                    setTimeout(function() {
                        $('.simple-feedback-wrapper').css('bottom', '0px');
                        setTimeout(function() {
                            $this.find('textarea').val('');
                            $this.find('input[type="submit"]').prop('disabled', false);
                            $('.simple-feedback-wrapper').find(':input, label').show();
                            $this.find('.simple-feedback-message').hide();
                        }, 1000);
                    }, 3000);
                }
            });
        });
        
    });
    
})(jQuery);