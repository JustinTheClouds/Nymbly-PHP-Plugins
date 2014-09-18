(function($) {
    
    // Show tab panel
    $('.admin-bar-tabs a').click(function() {
        $('.admin-bar-tab-content').hide();
        $($(this).attr('href')).show();
        $('#admin-bar-close-panel').css('display', 'inline-block');
    });
    
    // Close Tab panels
    $('#admin-bar-close-panel').click(function() {
        $('.admin-bar-tab-content').hide();
        $('#admin-bar-close-panel').hide();
    });
    
    // Show debug content
    $('.admin-bar-debug-title').click(function() {
        $(this).closest('.admin-bar-debug-wrap').find('.admin-bar-debug-content').toggle();
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
                    if(status == 100 || data['plugins/admin_bar'].completed === true) return reloadPage();
                    $('#admin-bar-tab-updates').empty().append(data['plugins/admin_bar'].status.join("<br />"));
                    status++;
                    checkUpdateStatus();
                });
            }, 500);
        }
        
        function reloadPage() {
            $('#admin-bar-tab-updates').append('<br />Reloading in 5 seconds...');
            setTimeout(function() {
                window.location.reload();
            }, 6000);
            return;
        }
        
        // Begin update ajax script
        $('#admin-bar-tab-updates').empty();
        checkUpdateStatus();
    });

})(jQuery);