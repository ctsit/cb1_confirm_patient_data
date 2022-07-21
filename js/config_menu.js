const jsonLintButton = $('<input type=\'button\' onclick=\'prettyPrint(this)\' value=\'Validate JSON\'></input>');
const sampleMap = `[
        "source_field_1",
        "source_field_2",
        "source_field_3"
]`;
$(document).ready(() => {
  print('ready');
  const sourceCodebook = `<a target='_blank' href='${app_path_webroot_full}${app_path_webroot.slice(1)}Design/data_dictionary_codebook.php?pid=${CBCPD.sourceProjectId}'><button>Source codebook</button></a>`;
  const targetCodebook = `<a target='_blank' href='${app_path_webroot_full}${app_path_webroot.slice(1)}Design/data_dictionary_codebook.php?pid=${CBCPD.thisProjectId}'><button>Target codebook</button></a>`;
  const $modal = $('#external-modules-configure-modal');
  let lastHoveredProjectID;

  /* Dynamically updating the href of the source codebook is accomplished by
     * capturing the last hovered project in the select dropdown and updating
     * the href when a new project is selected.

     * NOTE: Dynamically updating the href cannot be accomplished without the mouseenter event listener.
     * This is because the change event on the select list completes before updating, resulting
     * in the previous option being available rather than the new option that was selected. By keeping
     * track of the last hovered Project in the dropdown, the href can be properly updated.
    * */
  const setSourceCodebook = function () {
    // Attach mouseenter event listener to project list options to capture last hovered project
    $('body').on('mouseenter', 'li.select2-results__option', function (event) {
      const projectText = $(this).text();
      lastHoveredProjectID = $('tr[field="target_pid"]').find(`option:contains("${projectText}")`).attr('value');
    });

    // Attach change event listener to the project select list to update href
    $('tr[field="target_pid"]').find('select').on('change', (event) => {
      const updatedHref = `${app_path_webroot_full}${app_path_webroot.slice(1)}Design/data_dictionary_codebook.php?pid=${lastHoveredProjectID}`;
      $('tr[field=\'codebook_shortcuts\'] a').first().attr('href', updatedHref);
    });
  };

  $modal.on('show.bs.modal', function () {
    if ($(this).data('module') != CBCPD.modulePrefix) {
      return;
    }

    if (typeof ExternalModules.Settings.prototype.resetConfigInstancesOld === 'undefined') {
      ExternalModules.Settings.prototype.resetConfigInstancesOld = ExternalModules.Settings.prototype.resetConfigInstances;
    }

    ExternalModules.Settings.prototype.resetConfigInstances = function () {
      ExternalModules.Settings.prototype.resetConfigInstancesOld();
      if ($modal.data('module') != CBCPD.modulePrefix) {
        return;
      }
      // Force the descriptive field to show codebook buttons
      $('tr[field=\'codebook_shortcuts\']')
        .children(':first')
        .removeAttr('colspan')
        .next() // hijack existing empty td for buttons
        .html(sourceCodebook + targetCodebook)
        .attr('colspan', 2);

      const $mappingFields = $('textarea[name*=\'mapping___\']');
      $mappingFields
      // .attr('placeholder', sample_map)
        .attr('placeholder', sampleMap)
        .siblings().remove(); // prevent duplicate lint buttons for a mapping field
      $mappingFields.after(jsonLintButton.clone());
      // TODO: force validation of all JSON fields _before_ save

      // Dynamically update source codebook
      setSourceCodebook();
    };
  });
});

// function prettyPrint (element) {
//   const $field = $(element).prev()
//   const ugly = $field.val()
//   if (ugly) {
//     try {
//       const pretty = JSON.stringify(JSON.parse(ugly), undefined, 2)
//       $field.val(pretty)
//     } catch (err) {
//       if (err instanceof SyntaxError) {
//         alert('There is an error in your JSON syntax:\n' + err.message)
//       }
//     }
//   }
// }
