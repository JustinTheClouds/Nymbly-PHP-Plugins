(function($) {
    
    // Show tab panel
    $('.admin-bar-tabs a').click(function() {
        $('.admin-bar-tab-content').hide();
        $($(this).attr('href')).show();
    });
    
    // Close Tab panels
    $('#admin-bar-close-panel').click(function() {
        $('.admin-bar-tab-content').hide();
    });
    
    // Run updates
    $('#admin-bar-run-updates-button').click(function() {
        
        var status = 0;
        function checkUpdateStatus() {
            if(status == 'Completed') {
                alert('Completed');
                return;
            }
            setTimeout(function() {
                $('#admin-bar-tab-updates').append('<div>Sample log</div>');
                status++;
                if(status == 5) status = 'Completed';
                checkUpdateStatus();
            }, 1000);
        }
        
        // Begin update ajax script
        $('#admin-bar-tab-updates').empty();
        checkUpdateStatus();
    });

})(jQuery);