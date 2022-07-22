<?php

namespace CBC\ExternalModule;

use ExternalModules\AbstractExternalModule;
use DataEntry;
use MetaData;

use function Sabre\Uri\split;

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

        // collect source project's field labels if needed
        $source_fields_mapping = [];
        if ($this->getProjectSetting('limit_fields')) {
            $source_fields = $this->fetchMappings($instrument);
            // $source_fields = array_keys($mapping);

            /* FIXME: EM query does not like field_name IN
             * mysqli_result object is not behaving with fetch_all
             */
            // $sql = "SELECT field_name, element_label
            //     FROM redcap_metadata
            //     WHERE project_id = ?
            //         AND field_name IN (?)";
            // $source_fields_mapping = $this->framework->
            //                        query($sql,
            //                              [$target_pid,
            //                               implode(",`", $source_fields)
            //                              ]
            //                        )->fetch_all(MYSQLI_ASSOC);

            // HACK: fetch the entire data dictionary just to get the field labels
            // TODO: replace this with direct query for better performance
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
                $target_pid
                /*$delimiter=','*/
            );

            foreach ($source_fields_mapping as $k => $v) {
                $source_fields_mapping[$k] = $v['field_label'];
            }
        }

        $this->setJsSettings([
            'target_pid' => $target_pid,
            'ajaxpage' => $this->framework->getUrl('ajaxpage.php'),
            'limit_fields' => $this->framework->getProjectSetting('limit_fields'),
            'source_fields_mapping' => $source_fields_mapping
        ]);
        $this->includeJs('js/custom_data_search.js');
        // DataEntry::renderSearchUtility();

        include('data_confirm_modal.html');
        echo '</br>';
    }

    function getCaregiverInfo($record_id, $instrument)
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

        // eliminate event-level of array and promote fields
        $all_person_data = array_merge_recursive($redcap_data[$record_id]);

        $source_person_data = $all_person_data[0]; // only data from non-repeat events


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

        // replace coded field name with display name for UI
        foreach ($source_fields_mapping as $field_id => $field_attributes) {
            $field_value = isset($source_person_data[$field_id]) ? $source_person_data[$field_id] : '';
            $field_label = $field_attributes['field_label'];
            $field_type = $field_attributes['field_type'];

            // if a dropdown then add to field_labels to array
            if ($field_value != '' && $field_type == 'dropdown' || $field_type == 'radio') {
                $field_value = $this->get_select_choice_full_field_equivalent($field_value, $field_attributes['select_choices_or_calculations']);
            }

            $source_person_data[$field_label] = $field_value;
            unset($source_person_data[$field_id]);
        }

        return $source_person_data;
    }

    /**
     * Returns the full value equivalent of the coded field value
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
        return $select_choices_full_values[$index];
    }

    // Copied nearly exactly from the DataQuality class because it's a private function
    // TODO: utilize DateTimeRC::datetimeConvert, but this does all the lifting
    // private function convertDateFormat($field, $value)
    // {
    //     global $Proj;
    //     // Get field validation type, if exists
    //     $valType = $Proj->metadata[$field]['element_validation_type'];
    //     // If field is a date[time][_seonds] field with MDY or DMY formatted, then reformat the displayed date for consistency
    //     if (
    //         $value != '' && !is_array($value) && substr($valType, 0, 4) == 'date'
    //         && (substr($valType, -4) == '_mdy' || substr($valType, -4) == '_dmy')
    //     ) {
    //         // Get array of all available validation types
    //         $valTypes = getValTypes();
    //         $valTypes['date_mdy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
    //         $valTypes['date_dmy']['regex_php'] = $valTypes['date_ymd']['regex_php'];
    //         $valTypes['datetime_mdy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
    //         $valTypes['datetime_dmy']['regex_php'] = $valTypes['datetime_ymd']['regex_php'];
    //         $valTypes['datetime_seconds_mdy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
    //         $valTypes['datetime_seconds_dmy']['regex_php'] = $valTypes['datetime_seconds_ymd']['regex_php'];
    //         // Set regex pattern to use for this field
    //         $regex_pattern = $valTypes[$valType]['regex_php'];
    //         // Run the value through the regex pattern
    //         preg_match($regex_pattern, $value, $regex_matches);
    //         // Was it validated? (If so, will have a value in 0 key in array returned.)
    //         $failed_regex = (!isset($regex_matches[0]));
    //         if ($failed_regex) return $value;
    //         // Dates
    //         if ($valType == 'date_mdy') {
    //             $value = \DateTimeRC::date_ymd2mdy($value);
    //         } elseif ($valType == 'date_dmy') {
    //             $value = \DateTimeRC::date_ymd2dmy($value);
    //         } else {
    //             // Datetime and Datetime seconds
    //             list($this_date, $this_time) = explode(" ", $value);
    //             if ($valType == 'datetime_mdy' || $valType == 'datetime_seconds_mdy') {
    //                 $value = trim(\DateTimeRC::date_ymd2mdy($this_date) . " " . $this_time);
    //             } elseif ($valType == 'datetime_dmy' || $valType == 'datetime_seconds_dmy') {
    //                 $value = trim(\DateTimeRC::date_ymd2dmy($this_date) . " " . $this_time);
    //             }
    //         }
    //     }
    //     // Return the value
    //     return $value;
    // }

    protected function includeJs($file)
    {
        echo '<script src="' . $this->getUrl($file) . '"></script>';
    }

    protected function setJsSettings($settings)
    {
        echo '<script>CBCPD = ' . json_encode($settings) . ';</script>';
    }

    function digNestedData($subject_data_array, $key)
    {
        $value = null;
        if (property_exists($subject_data_array, $key)) {
            $value = $subject_data_array->{$key};
        } else {
            // keys nested in objects were not being found
            array_walk_recursive(
                $subject_data_array,
                function ($v, $k) use ($key, &$value) {
                    if ("$key" == "$k") {
                        $value = $v;
                    }
                }
            );
        }

        return $value;
    }

    function fetchMappings($instrument)
    {
        $target_forms = $this->framework->getProjectSetting('show_on_form');
        $instrument_index = array_search($instrument, $target_forms);
        $mapping = json_decode($this->framework->getProjectSetting('mapping')[$instrument_index], true);
        return $mapping;
    }
}
