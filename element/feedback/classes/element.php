<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the customcert element grade's core interaction API.
 *
 * @package    customcertelement_grade
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_feedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Grade - Course
 */
define('CUSTOMCERT_FEEDBACK_COURSE', '0');

/**
 * The customcert element grade's core interaction API.
 *
 * @package    customcertelement_grade
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {

    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        global $COURSE;

        // Get the grade items we can display.
        $feedbackitems = array();
        $feedbackitems = \mod_customcert\element_helper::get_feedback_items($COURSE);

        // The feedback items.
        $mform->addElement('select', 'feedbackitem', get_string('feedbackitem', 'customcertelement_feedback'), $feedbackitems);
        $mform->addHelpButton('feedbackitem', 'feedbackitem', 'customcertelement_feedback');

        parent::render_form_elements($mform);
    }

    // private function get_feedback_items($COURSE) {
    //     global $PAGE;
    //     // print nl2br(print_r($COURSE, true));
    //     // print nl2br(print_r($PAGE, true));
    //     $cm = 
    //     $feedback = $PAGE->activityrecord;
    //     $feedbackstructure = new mod_feedback_structure($feedback, $cm);

    // }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data.
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        // Array of data we will be storing in the database.
        $arrtostore = array(
            'feedbackitem' => $data->feedbackitem
        );

        // Encode these variables before saving into the DB.
        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $courseid = \mod_customcert\element_helper::get_courseid($this->id);

        // Decode the information stored in the database.
        $feedbackinfo = json_decode($this->get_data());
        $itemId = explode(':', $feedbackinfo->feedbackitem);
        $itemId = $itemId[1];

        // If we are previewing this certificate then just get a random value
        if ($preview) {
            $itemInfo = \mod_customcert\element_helper::get_feedback_item_info($itemId);
            $feedbackValue = $this->generate_feedback_value($itemInfo);
        } else {
            // Get the entered feedback value and display it here
            $feedbackValue = \mod_customcert\element_helper::get_feedback_item_value($itemId, $user);
            
        }
        \mod_customcert\element_helper::render_content($pdf, $this, $feedbackValue);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        global $COURSE;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        // Decode the information stored in the database.
        $feedbackinfo = json_decode($this->get_data());
        $itemId = explode(':', $feedbackinfo->feedbackitem);
        $itemId = $itemId[1];
        $itemInfo = \mod_customcert\element_helper::get_feedback_item_info($itemId);

        $renderValue = $this->generate_feedback_value($itemInfo);
        return \mod_customcert\element_helper::render_html_content($this, $renderValue);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \mod_customcert\edit_element_form $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        // Set the item and format for this element.
        if (!empty($this->get_data())) {
            $feedbackinfo = json_decode($this->get_data());

            $element = $mform->getElement('feedbackitem');
            $element->setValue($feedbackinfo->feedbackitem);
        }

        parent::definition_after_data($mform);
    }

    /**
     * This function is responsible for handling the restoration process of the element.
     *
     * We will want to update the course module the grade element is pointing to as it will
     * have changed in the course restore.
     *
     * @param \restore_customcert_activity_task $restore
     */
    public function after_restore($restore) {
        global $DB;

        $feedbackinfo = json_decode($this->get_data());
        if ($newitem = \restore_dbops::get_backup_ids_record($restore->get_restoreid(), 'course_module', $feedackinfo->feedbackitem)) {
            $gradeinfo->gradeitem = $newitem->newitemid;
            $DB->set_field('customcert_elements', 'data', $this->save_unique_data($feedbackinfo), array('id' => $this->get_id()));
        }
    }

    /**
     * Private function to generate random feedback data for display on the certificate
     * 
     * @param \Stdclass representation of the feedback_item table
     * @return mixed random value
     */
    private function generate_feedback_value($feedbackItem) {
        $renderValue = null;
        if ($feedbackItem->typ == 'numeric') {
            $renderValue = '100';
            // We have a range of numerics
            if ( strpos($feedbackItem->presentation, '|') ) {
                $itemRange = explode('|', $feedbackItem->presentation);
                $renderValue = rand($itemRange[0], $itemRange[1]);
            } else {
                $renderValue = 100;
            }
            
        }
        return $renderValue;
    }
}
