/* eslint-disable no-restricted-syntax */
/**
 *
 * @param {string} copyData
 */
function showDataConfirmModal(json) {
  $('#dialog-data-stp').dialog('open');

  // clear the rows from any previous search
  $('#stp-modal-table').empty();

  if (json) {
    for (const [index, person] of json.entries()) {
    // <th>Patient ${index + 1} Demographics</th>
      $('#stp-modal-table').append(`
      <thead>
          <th>Patient Demographics</th>
          <th>Value</th>
      </thead>
      <tbody id='patient-${index}'></tbody>
    `);

      // Add the rows from the current search
      for (const [key, value] of Object.entries(person)) {
        $(`#patient-${index}`).append(`
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
  } else {
    $('#modal-data-found').hide();
    $('#modal-no-data-found').show();
  }
}

// `GET` Patient demographic data from `ajaxpage.php`
const getPatientInfo = (recordId) => {
  const urlParams = new URLSearchParams(window.location.search);
  const { ajaxPage } = CB1;
  $.get({
    url: ajaxPage,
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
      .html('Verify Patient')
      .css(
        {
          'margin-left': '60%',
        },
      )
      .attr(
        {
          type: 'button',
          id: 'verify-patient',
          class: 'btn btn-info btn-sm',
        },
      ),
  );

  // Add on-click listener to demographic check button
  $('#verify-patient').on('click', () => {
    // `recordId` is the equivalent of ADC Subject ID for the ADRC project
    const { adcSubjectId } = CB1;
    const recordId = $(`#${adcSubjectId}-tr input`).val();
    if (!recordId) {
      alert('ADC Subject ID (ptid) is required.');
      return;
    }

    getPatientInfo(recordId);
  });
});

function hideDataConfirmModal(approved) {
  const { verifiedOnId } = CB1;

  // close the modal
  $('#dialog-data-stp').dialog('close');

  // Set the date of the verified on field
  if (approved) {
    $(`#${verifiedOnId}-tr input`).datepicker('setDate', new Date());
    return;
  }

  $(`#${verifiedOnId}-tr input`).datepicker('setDate', null);
  alert('The incorrect ADB Subject ID (ptid) was entered. Please enter the correct ptid before continuing.');
}
