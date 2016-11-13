$(document).ready(function () {
    $('#casSettings').tabs();


    function saveSettings() {
        var post = $('#casSettings #cas').serialize();
        $.post(OC.generateUrl('/apps/user_cas/settings'), post);
    }

    $("#casSettings #form > input[type='submit']").on('click', function (event) {
        saveSettings();
    });

    /*$('#registered_user_group').change(saveSettings);
     $('#allowed_domains').change(saveSettings);
     $('#registration').keypress(function (event) {
     if (event.keyCode === 13) {
     event.preventDefault();
     }
     });*/
});
