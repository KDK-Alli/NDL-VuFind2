finna.favorites = (function() {
    var form = $('#favorites-import-form');
    var selectFileInput = $('input[name=favorites-file]');
    var filenameInput = $('#filename');
    var submitBtn = form.find('button');
    var modalBody = form.parents('.modal-body');

    submitBtn.attr('disabled', true);

    selectFileInput.on('change', function(e) {
        if (e.target.files.length === 0) {
            return;
        }

        var file = e.target.files[0];
        filenameInput.val(file.name);
        submitBtn.attr('disabled', false);
    });

    var showInfo = function(response) {
        form.after(response.data);
    };

    var upload = function() {
        var spinner = $('<i>').addClass('fa fa-spinner fa-spin');
        submitBtn.attr('disabled', true);
        modalBody.prepend(spinner);
        modalBody.find('.alert').remove();

        var formData = new FormData(form.get(0));
        $.ajax({
            type: 'POST',
            url: VuFind.path + '/AJAX/JSON?method=importFavorites',
            processData: false,
            contentType: false,
            data: formData,
            dataType: 'json',
            success: showInfo,
            error: showInfo,
            complete: function() {
                submitBtn.attr('disabled', false);
                spinner.remove();
            }
        });
    };

    return {
        upload: upload
    };
})(finna);

