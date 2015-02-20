<?php
// This file is part of the plugin Block Badgeawarder
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
 * File containing setp 1 of the upload form
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Upload a file CVS file with badge information.
 *
 * @package     block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_badgeawarder_step1_form extends moodleform {

    /**
     *
     * The standard form definiton.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', null, get_string('upload'));
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'contextid', '');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('filepicker', 'badgecsv', get_string('file'), null, null);
        $mform->addRule('badgecsv', null, 'required');

        $samplecsv = html_writer::link(new moodle_url('/blocks/badgeawarder/badge_upload_sample.csv'),
            get_string('samplecsv', 'block_badgeawarder'));
        $mform->addElement('static', 'samplecsv', '', $samplecsv);

        $config = get_config('block_badgeawarder');

        if (!empty($config->showextendedoption) && ($config->showextendedoption == 1)) {
            $choices = csv_import_reader::get_delimiter_list();
            $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'block_badgeawarder'), $choices);
            
            if (!empty($config->defaultdelimiter)) {
                $mform->setDefault('delimiter_name', $config->defaultdelimiter);
            } else if (array_key_exists('cfg', $choices)) {
                $mform->setDefault('delimiter_name', 'cfg');
            } else if (get_string('listsep', 'langconfig') == ';') {
                $mform->setDefault('delimiter_name', 'semicolon');
            } else {
                $mform->setDefault('delimiter_name', 'comma');
            }

            $choices = core_text::get_encodings();
            $mform->addElement('select', 'encoding', get_string('encoding', 'block_badgeawarder'), $choices);

            if (!empty($config->defaultencoding)) {
                $mform->setDefault('encoding', $config->defaultencoding);
            } else {
                $mform->setDefault('encoding', 'UTF-8');
            }

            $choices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
            $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'block_badgeawarder'), $choices);
            $mform->setType('previewrows', PARAM_INT);
            $mform->addHelpButton('previewrows', 'rowpreviewnum', 'block_badgeawarder');
            if (!empty($config->defaultpreviewrows)) {
                $mform->setDefault('previewrows', $config->defaultpreviewrows);
            } else {
                $mform->setDefault('previewrows', '100');
            }

        
            $mform->addElement('header', 'importoptionshdr', get_string('importoptions', 'block_badgeawarder'));
            $mform->setExpanded('importoptionshdr', true);

            $choices = array(
            block_badgeawarder_processor::MODE_CREATE_NEW => get_string('awardnew', 'block_badgeawarder'),
            block_badgeawarder_processor::MODE_CREATE_ALL => get_string('awardall', 'block_badgeawarder'),
            block_badgeawarder_processor::MODE_UPDATE_ONLY => get_string('awardexisting', 'block_badgeawarder'));

            $mform->addElement('select', 'mode', get_string('mode', 'block_badgeawarder'), $choices);
            if (!empty($config->defaultuploadtype)) {
                $mform->setDefault('mode', $config->defaultuploadtype);
            } else {
                $mform->setDefault('mode', block_badgeawarder_processor::MODE_CREATE_ALL);
            }
            $mform->addHelpButton('mode', 'mode', 'block_badgeawarder');
        } else {
            // Set delimiter.
            if (!empty($config->defaultdelimiter)) {
                $mform->addElement('hidden', 'delimiter_name', $config->defaultdelimiter);
            } else {
                $mform->addElement('hidden', 'delimiter_name', 'comma');
            }
            $mform->setType('delimiter_name', PARAM_ALPHA);

            // Set encoding.
            if (!empty($config->defaultencoding)) {
                $mform->addElement('hidden', 'encoding', $config->defaultencoding);
            } else {
                $mform->addElement('hidden', 'encoding', 'UTF-8');
            }
            $mform->setType('encoding', PARAM_RAW);

            // Set previewrows.
            if (!empty($config->defaultpreviewrows)) {
                $mform->addElement('hidden', 'previewrows', $config->defaultpreviewrows);
            } else {
                $mform->addElement('hidden', 'previewrows', '100');
            }
            $mform->setType('previewrows', PARAM_INT);

            // Set upload type.
            if (!empty($config->defaultuploadtype)) {
                $mform->addElement('hidden', 'mode', $config->defaultuploadtype);
            } else {
                $mform->addElement('hidden', 'mode', block_badgeawarder_processor::MODE_CREATE_ALL);
            }
            $mform->setType('mode', PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('preview'));
    }
}
