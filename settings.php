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
        'block_badgeawarder/allowuploadtypechoosing',
        get_string('allowuploadtypechoosing', 'block_badgeawarder'),
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
}