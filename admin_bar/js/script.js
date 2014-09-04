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
    $('#admin-bar-run-updates-button').click(function(event) {
        
        event.preventDefault();
        
        $.ajax({
            async: true,
            type: "POST",
            url: $(this).closest('form').attr('action') + '&json_request=1',
            data: $(this).closest('form').serialize()
        });
        
        var status = 0;
        function checkUpdateStatus() {
            setTimeout(function() {
                $.ajax({
                    url: '?json_request=1&plugins[admin_bar][action]=checkUpdatingStatus',
                    async: true,
                    dataType: 'json'
                }).done(function(data) {
                    if(status == 100 || data.completed === true) return;
                    $('#admin-bar-tab-updates').empty().append(data.status.join("<br />"));
                    status++;
                    checkUpdateStatus();
                });
            }, 500);
        }
        
        // Begin update ajax script
        $('#admin-bar-tab-updates').empty();
        checkUpdateStatus();
    });

})(jQuery);