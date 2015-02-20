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

class block_badgeawarder_step2_form extends moodleform {

    /**
     * The standard form definiton.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $data  = $this->_customdata['data'];

        $mform->addElement('hidden', 'courseid', $data['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'importid', $data['importid']);
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'mode', $data['mode']);
        $mform->setType('mode', PARAM_INT);

        if (isset($data['mode']) && $data['mode'] != 3) {
            $mform->addElement('header', 'usersettings', get_string('usersettings', 'block_badgeawarder'));
            $mform->setExpanded('usersettings', true);
            $countries = get_string_manager()->get_list_of_countries();
            array_unshift($countries, get_string('selectcountry', 'block_badgeawarder'));

            $mform->addElement('select', 'country', get_string('country'), $countries);
            $mform->setType('country', PARAM_TEXT);

            $mform->addElement('text', 'city', get_string('city'));
            $mform->setType('city', PARAM_TEXT);
            $mform->disabledIf('city', 'mode', 'eq', 3);
        }

        $this->add_action_buttons(true, get_string('awardbadges', 'block_badgeawarder'));
    }
}
