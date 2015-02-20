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
 * Version details
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot .'/blocks/badgeawarder/processor.php');

if ($ADMIN->fulltree) {
    $checkbox = new admin_setting_configcheckbox(
        'block_badgeawarder/showextendedoption',
        get_string('showextendedoption', 'block_badgeawarder'),
        '',
        0,
        1,
        0
    );
    $settings->add($checkbox);

    $choices = array(
        block_badgeawarder_processor::MODE_CREATE_NEW => get_string('awardnew', 'block_badgeawarder'),
        block_badgeawarder_processor::MODE_CREATE_ALL => get_string('awardall', 'block_badgeawarder'),
        block_badgeawarder_processor::MODE_UPDATE_ONLY => get_string('awardexisting', 'block_badgeawarder')
    );

    $select = new admin_setting_configselect(
        'block_badgeawarder/defaultuploadtype',
        get_string('defaultuploadtype', 'block_badgeawarder'),
        '',
        block_badgeawarder_processor::MODE_CREATE_ALL,
        $choices
    );

    $settings->add($select);

    $choices = csv_import_reader::get_delimiter_list();

    $select = new admin_setting_configselect(
        'block_badgeawarder/defaultdelimiter',
        get_string('defaultdelimiter', 'block_badgeawarder'),
        '',
        'comma',
        $choices
    );

    $settings->add($select);

    $choices = core_text::get_encodings();

    $select = new admin_setting_configselect(
        'block_badgeawarder/defaultencoding',
        get_string('defaultencoding', 'block_badgeawarder'),
        '',
        'UTF-8',
        $choices
    );
    
    $settings->add($select);

    $choices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);

    $select = new admin_setting_configselect(
        'block_badgeawarder/defaultpreviewrows',
        get_string('defaultpreviewrows', 'block_badgeawarder'),
        '',
        '100',
        $choices
    );
    
    $settings->add($select);
}
