<?php

namespace CB1\ExternalModule;

use ExternalModules\AbstractExternalModule;

class ExternalModule extends AbstractExternalModule
{

    function redcap_every_page_top($project_id)
    {
        if ($project_id && strpos(PAGE, 'ExternalModules/manager/project.php') !== false) {
            $this->setJsSettings([
                'modulePrefix' => $this->PREFIX,
                'sourceProjectId' => $this->framework->getProjectSetting('target_pid'),
                'thisProjectId' => $project_id

            ]);
            $this->includeJs('js/config_menu.js');
        }
    }

    function redcap_data_entry_form_top($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance)
    {
        // only spawn search interface on specified form
        if (!in_array($instrument, (array) $this->framework->getProjectSetting('show_on_form'))) return;
        $target_pid = $this->framework->getProjectSetting('target_pid');

        $this->setJsSettings([
            'target_pid' => $target_pid,
            'ajaxPage' => $this->framework->getUrl('ajaxpage.php'),
            'adcSubjectId' => $this->framework->getProjectSetting('adc_subject_id')[0],
            'verifiedOnId' => $this->framework->getProjectSetting('verified_on')[0],
        ]);

        $this->includeJs('js/app.js');

        include('data_confirm_modal.html');
        echo '</br>';
    }

    function getPatientInfo($record_id, $instrument)
    {

        if (!$record_id | !$instrument) return false;

        $target_project_id = $this->framework->getProjectSetting('target_pid');

        $source_fields = $this->fetchMappings($instrument);

        $get_data = [
            'project_id' => $target_project_id,
            'records' => $record_id,
            // 'events' => $event,
            'events' => [0],
            'fields' => $source_fields,
        ];

        $redcap_data = \REDCap::getData($get_data);

        // $redcap_data contains each event


        /*
         * this code left here and commented out incase I need it
        foreach($redcap_data as $record_id => $events) {
            foreach($events as $event_id => $fields) {
                if ($fields = array_filter($fields)) { // make sure there's anything at all before continuing
                    // do something if needed
                }
            }
        }
        */

        $all_person_data = array_filter(array_merge_recursive($redcap_data[$record_id]));
        $unique_persons = array_unique($all_person_data, SORT_REGULAR);

        // translate coded names to labels
        $source_fields_mapping = \MetaData::getDataDictionary(/*$returnFormat= */
            'array',
            /*$returnCsvLabelHeaders= */
            true,
            /*$fields= */
            $source_fields,
            /*$forms= */
            array(),
            /*$isMobileApp= */
            false,
            /*$draft_mode= */
            false,
            /*$revision_id= */
            null,
            /*$project_id_override= */
            $target_project_id
            /*$delimiter=','*/
        );

        // TODO(mbentz-uf): Determine unique persons and return an array of persons.
        // The loop is wrong. Instead of looping through all source field mappings it should be used as a lookup instead
        // Start here! Revisit if nested loop can be removed.
        foreach ($unique_persons as $index => $person) {
            // replace coded field value with full field value
            foreach ($source_fields_mapping as $field_id => $field_attributes) {
                $field_value = $person[$field_id];

                if (!isset($field_value)) {
                    continue;
                }

                $field_label = $field_attributes['field_label'];
                $field_type = $field_attributes['field_type'];

                // if a dropdown then add to field_labels to array
                if ($field_value != '' && $field_type == 'dropdown' || $field_type == 'radio') {
                    $field_value = $this->get_select_choice_full_field_equivalent($field_value, $field_attributes['select_choices_or_calculations']);
                }

                $unique_persons[$index][$field_label] = $field_value;
                unset($unique_persons[$index][$field_id]);
            }
        }


        return $unique_persons;
    }

    /**
     * Returns the full field value equivalent of the coded field value
     * 
     * @param string $needle The coded field value to search for
     * @param string $select_choices The `select_choices_or_calculations` of a field
     * @return string
     */
    private function get_select_choice_full_field_equivalent(string $needle, string $select_choices): string
    {
        // `select_choices_or_calculations` are '|' delimeted. Two samples are:
        // "1, 1 Male|2, 2 Female"
        // "0, 0 No (If No, <b>SKIP TO QUESTION 4</b>)|1, 1 Yes|9, 9 Unknown (If Unknown, <b>SKIP TO QUESTION 4</b>)"
        $exploded_select_choices = explode('|', $select_choices);

        // Iterate over all select choices and create two arrays for quick lookup
        // A select choice has the format of: "1, 1 Male".
        // The data in front of the "," is the coded value and the data after is the full value
        $select_choices_coded_values = [];
        $select_choices_full_values = [];
        foreach ($exploded_select_choices as &$choice) {
            list($coded_value, $full_value) = explode(',', $choice);
            array_push($select_choices_coded_values, $coded_value);
            array_push($select_choices_full_values, $full_value);
        }

        $index = array_search($needle, $select_choices_coded_values);

        // Return the full value equivalent
        return preg_replace('/\d{1,}\s{1}/', '', $select_choices_full_values[$index]);
    }

    protected function includeJs($file)
    {
        echo '<script src="' . $this->getUrl($file) . '"></script>';
    }

    protected function setJsSettings($settings)
    {
        echo '<script>CB1 = ' . json_encode($settings) . ';</script>';
    }

    function fetchMappings($instrument)
    {
        $target_forms = $this->framework->getProjectSetting('show_on_form');
        $instrument_index = array_search($instrument, $target_forms);
        $mapping = json_decode($this->framework->getProjectSetting('mapping')[$instrument_index], true);
        return $mapping;
    }
}
