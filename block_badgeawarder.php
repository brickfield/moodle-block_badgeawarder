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

class block_badgeawarder extends block_base {

    public function init() {
        $this->title = get_string('blockname', 'block_badgeawarder');
    }

    public function applicable_formats() {
        return array('course' => true);
    }

    public function specialization() {
        $this->title = get_string('blockname', 'block_badgeawarder');
    }

    public function has_config() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function get_content() {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        if (isset($this->config)) {
            $config = $this->config;
        } else {
            $config = get_config('blocks/badgeawarder');
        }

        $this->content = new stdClass;
        $this->content->text = '';

        if (empty($CFG->enablebadges)) {
            $this->content->text .= get_string('badgesdisabled', 'badges');
            return $this->content;
        }

        $context = context_course::instance($this->page->course->id);

        if (has_capability('block/badgeawarder:uploadcsv', $context)) {
            $linkurl = new moodle_url('/blocks/badgeawarder/badgeawarder.php', array('courseid' => $this->page->course->id));
            $this->content->text .= html_writer::link($linkurl, get_string('uploadbadgecsv', 'block_badgeawarder'));
        }

        $this->content->footer = '';

        return $this->content;
    }
}
