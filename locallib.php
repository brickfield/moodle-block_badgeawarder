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
 * Contains the set up functions.
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create process forms page header
 *
 * @param object $course the course object.
 * @param object $context the context.
 */
function block_badgeawarder_page_header($course, $context) {
    global $PAGE;
    $badgeawarder = new moodle_url('/blocks/badgeawarder/badgeawarder.php', ['courseid' => $course->id]);
    $PAGE->set_url('/blocks/badgeawarder/badgeawarder.php', ['courseid' => $course->id, 'contextid' => $context->id]);
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('badgecsv', 'block_badgeawarder'));
    $PAGE->set_heading(get_string('badgecsv', 'block_badgeawarder'));

    $PAGE->set_title(get_string('badgecsv', 'block_badgeawarder'));
    $PAGE->navbar->add(get_string('uploadcsv', 'block_badgeawarder'), $badgeawarder, navigation_node::TYPE_CUSTOM);
}

/**
 * Resolves the import mode to use, enforcing the admin-configured default when extended
 * options are turned off instead of trusting a client-supplied hidden form value.
 *
 * @param int $mode the mode requested by the client.
 * @return int the mode to actually use.
 */
function block_badgeawarder_resolve_mode($mode) {
    $config = get_config('block_badgeawarder');
    if (!empty($config->showextendedoption)) {
        return $mode;
    }
    if (!empty($config->defaultuploadtype)) {
        return (int) $config->defaultuploadtype;
    }
    return block_badgeawarder_processor::MODE_CREATE_ALL;
}
