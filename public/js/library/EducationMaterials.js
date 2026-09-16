
$( document ).ready(function() {
//Удаление материала
    $(document)
        .off('click.educationalMaterials', '.deleteEducationalMaterial')
        .on('click.educationalMaterials', '.deleteEducationalMaterial', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var button = $(this);
        if (button.data('deleting')) {
            return false;
        }

        var x = confirm("Удалить материал?");
        if (x) {
            button.data('deleting', true);
            var id = button.attr("id");
            var token = button.attr("value");
            $.ajax(
                {
                    url: "/library/educationalMaterials/" + id + "/delete",
                    type: 'DELETE',
                    dataType: "JSON",
                    data: {
                        "id": id,
                        "_method": 'DELETE',
                        "_token": token
                    },
                    success: function (data) {
                        if (data.msg != 'ok') {
                            alert(data.msg)
                        } else {

                            $('[name = "delete'+data.id+'"]').parent().parent().hide();
                        }
                    },
                    error: function (xhr) {
                        button.data('deleting', false);
                        var message = 'Не удалось удалить материал.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message += ' ' + xhr.responseJSON.message;
                        }
                        alert(message);
                    }
                });
        }
        else
            return false;
    });
});
