/* eslint-disable no-restricted-syntax */
/**
 *
 * @param {string} copyData
 */
function showDataConfirmModal(json) {
  // show the modal
  $('#dialog-data-stp').dialog('open');

  // hold onto the data in its current form
  // $('#dialog-data-stp').data('copyData', copyData);

  // clear the rows from any previous search
  $('#stp-modal-table').empty();

  for (const [index, person] of json.entries()) {
    $('#stp-modal-table').append(`
      <thead>
          <th>Caregiver ${index + 1}: Field</th>
          <th>Value</th>
      </thead>
      <tbody id='caregiver-${index}'></tbody>
    `);

    // Add the rows from the current search
    for (const [key, value] of Object.entries(person)) {
      $(`#caregiver-${index}`).append(`
        <tr>
          <td>${key}</td>
          <td>${value}</td>
        </tr>
      `);
    }

    // Add spacing
    $('#stp-modal-table').append(`
      <br>
      <br>
    `);
  }
}

// `GET` Caregiver demographic data from `ajaxpage.php`
const getCaregiverInfo = (recordId) => {
  const urlParams = new URLSearchParams(window.location.search);
  $.get({
    url: CB1.ajaxpage,
    data: {
      recordId,
      instrument: urlParams.get('page'),
    },
  })
    .done((data) => {
      const responseData = JSON.parse(data);
      showDataConfirmModal(responseData);
    });
};

$(document).ready(() => {
  // setting up the dialog for the search confirmation before copying
  $('#dialog-data-stp').dialog({
    autoOpen: false,
    draggable: true,
    resizable: true,
    closeOnEscape: true,
    minWidth: 500,
    modal: true,
    open() {
      // clicking the background of the modal closes the modal
      $('.ui-widget-overlay').bind('click', () => {
        $('#dialog-data-stp').dialog('close');
      });
    },
  });

  const jqTitleRow = $('#contextMsg > div');

  // TODO(mbentz-uf): Replace hardcoded margin percentage with flex-box
  jqTitleRow.append(
    $('<button />')
      .html('Verify Caregiver')
      .css(
        {
          'margin-left': '60%',
        },
      )
      .attr(
        {
          type: 'button',
          id: 'Check',
          class: 'btn btn-info btn-sm',
        },
      ),
  );

  // Add on-click listener to demographic check button
  $('#Check').on('click', () => {
    // `recordId` is the equivalent of ADC Subject ID for the ADRC project
    const { adcSubjectId } = CB1;
    const recordId = $(`#${adcSubjectId}-tr input`).val();
    getCaregiverInfo(recordId);
  });
});

// function selectFromDropdown (key, value) {
//   const $targetRow = $(`tr[sq_id='${key}']`)
//   const $ac_target_field = $($targetRow.find('input')[0]) // ac = auto complete
//   const $select_field = $(`select[name='${key}']`)

//   // used to handle cases where the value provided is the displayed value of the desired option,
//   // rather than the coded value (value attribute)
//   const displayed_option_value = $select_field
//     .children()
//     .filter((i, e) => {
//       return ($(e).html() == value)
//     })
//     .val()

//   // autocomplete fields
//   if ($ac_target_field.attr('class') == 'x-form-text x-form-field rc-autocomplete ui-autocomplete-input') {
//     // the non-coded value must be put in the text box to allow the user to see the pipe occured
//     // if displayed_value is undefined, this function sets the value to nothing
//     const displayed_value = $select_field
//       .children(`[value='${value}']`)
//       .html()
//     $ac_target_field.val(displayed_value)

//     if ($ac_target_field.val() != value && displayed_option_value != undefined) {
//       // TODO: handle the possibilty that this value could go in an "other" field behind branching logic
//       $ac_target_field.val(value)
//     }
//     return
//   }

//   // non autocomplete fields
//   $select_field.val(value)
//   if ($select_field.val() != value && displayed_option_value != undefined) {
//     // TODO: handle the possibilty that this value could go in an "other" field behind branching logic
//     $select_field.val(displayed_option_value)
//   }
// }

// function hideDataConfirmModal (isCopy) {
//   // close the modal
//   $('#dialog-data-stp').dialog('close')
//   // get the data from the html
//   const copydata = $('#dialog-data-stp').data('copyData')
//   if (isCopy > 0) {
//     // copy the data into the form
//     pasteValues(copydata)
//   }
//   // clean up afterwards
//   $('#dialog-data-stp').removeData('copyData')
// }
