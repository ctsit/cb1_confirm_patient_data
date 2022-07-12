$(document).ready(function () {
    // TODO(mbentz-uf): learn what these lines do
    if (CBCPD.limit_fields) {
        // field selector options are initially for the target, not source project
        // replace them with only those defined in the project config
        $('#field_select').empty();
        $.each(CBCPD.source_fields_mapping, function (key, label) {
            $('#field_select').append($('<option></option>').val(key).html(label));
        });
    } else {
        $('#field_select').parent().parent().hide();
    }

    // setting up the dialog for the search confirmation before copying
    $('#dialog-data-stp').dialog({
        autoOpen: false,
        draggable: true,
        resizable: true,
        closeOnEscape: true,
        minWidth: 500,
        modal: true,
        open: function () {
            // clicking the background of the modal closes the modal
            $('.ui-widget-overlay').bind('click', function () {
                $('#dialog-data-stp').dialog('close');
            })
        }
    });

    // TODO(mbentz-uf): make this configurable
    // Insert a button to check demographics
    $('#form-title').after(
    // $('#form_menu_description_label').after(
        $('<button />')
            .html('Check')
            .attr(
                {
                    type: 'button',
                    id: 'Check',
                    onlcick: 'showPatientInfo()',
                    class: 'btn btn-info btn-sm'
                }
            )
    );

    // Add on-click listener to demographic check button
    $('#Check').on('click', () => {
        showPatientInfo()
    });
});

function showPatientInfo() {
    // FIXME
    let id = $($('#ptid-tr').find('input')[0]).val()
    ajaxGet(id);
}

function ajaxGet(record_id) {
    const urlParams = new URLSearchParams(window.location.search);
    $.get({
        url: CBCPD.ajaxpage,
        data: {
            recordId: record_id,
            instrument: urlParams.get('page')
        },
    })
        .done(function (data) {
            response_data = JSON.parse(data);
            showDataConfirmModal(response_data);
        });
}

function pasteValues(values) {
    console.log(values);
    // for (let [key, value] of Object.entries(values)) {
    //     let $target_field = $(`input[name='${key}']`);
    //     if ($target_field.length == 0) {
    //         // not found by name attr, field may be present as a dropdown
    //         selectFromDropdown(key, value);
    //     }
    //     // radio and regular text boxes
    //     if ($target_field.attr('class') == 'hiddenradio') {
    //         // collect all radio fields in all layouts
    //         let $inputs = $target_field.siblings('[class*="choice"]');
    //         // select radio assuming target coded value matches source coded value
    //         $inputs.find(`[value='${value}']`).click();
    //     } else {
    //         // FIXME: does not honor desired date formatting
    //         $target_field.val(`${value}`);
    //         $target_field.blur();
    //     }
    // }
}

function selectFromDropdown(key, value) {
    const $target_row = $(`tr[sq_id='${key}']`);
    const $ac_target_field = $($target_row.find('input')[0]); // ac = auto complete
    const $select_field = $(`select[name='${key}']`);

    // used to handle cases where the value provided is the displayed value of the desired option,
    // rather than the coded value (value attribute)
    const displayed_option_value = $select_field
        .children()
        .filter((i, e) => {
            return ($(e).html() == value);
        })
        .val();

    // autocomplete fields
    if ($ac_target_field.attr('class') == 'x-form-text x-form-field rc-autocomplete ui-autocomplete-input') {
        // the non-coded value must be put in the text box to allow the user to see the pipe occured
        // if displayed_value is undefined, this function sets the value to nothing
        const displayed_value = $select_field
            .children(`[value='${value}']`)
            .html();
        $ac_target_field.val(displayed_value);

        if ($ac_target_field.val() != value && displayed_option_value != undefined) {
            // TODO: handle the possibilty that this value could go in an "other" field behind branching logic
            $ac_target_field.val(value);
        }
        return;
    }

    // non autocomplete fields
    $select_field.val(value);
    if ($select_field.val() != value && displayed_option_value != undefined) {
        // TODO: handle the possibilty that this value could go in an "other" field behind branching logic
        $select_field.val(displayed_option_value);
    }
}

function showDataConfirmModal(copyData) {
    // show the modal
    $('#dialog-data-stp').dialog('open');

    // hold onto the data in its current form
    $('#dialog-data-stp').data('copyData', copyData);

    // clear the rows from any previous search
    $('#body-for-stp-modal').empty();
    for (let [key, value] of Object.entries(copyData)) {
        // Add the rows from the current search
        $('#body-for-stp-modal').append('<tr><td>' + key + '</td><td>' + value + '</td></tr>');
    }

}

function hideDataConfirmModal(isCopy) {
    // close the modal
    $('#dialog-data-stp').dialog('close');
    // get the data from the html
    copydata = $('#dialog-data-stp').data('copyData');
    if (isCopy > 0) {
        // copy the data into the form
        pasteValues(copydata);
    }
    // clean up afterwards
    $('#dialog-data-stp').removeData('copyData');
}
