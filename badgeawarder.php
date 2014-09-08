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
require(__DIR__ .'/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot .'/blocks/badgeawarder/forms/step1_form.php');
require_once($CFG->dirroot .'/blocks/badgeawarder/forms/step2_form.php');
require_once($CFG->dirroot .'/blocks/badgeawarder/processor.php');
require_once($CFG->dirroot .'/blocks/badgeawarder/tracker.php');
require_once('locallib.php');

$courseid  = optional_param('courseid', 0, PARAM_INT);
$importid = optional_param('importid', '', PARAM_INT);
$mode = optional_param('mode', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

if ($courseid == 0) {
    redirect(new moodle_url('/'));
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($courseid);

if (!has_capability('block/badgeawarder:uploadcsv', $context)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

$returnurl = new moodle_url('/course/view.php', array('id' => $course->id));

if (empty($importid)) {
    $mform1 = new block_badgeawarder_step1_form(null, array('courseid' => $course->id));
    if ($form1data = $mform1->is_cancelled()) {
        if (!empty($cir)) {
            $cir->cleanup(true);
        }
        redirect($returnurl);
    } else if ($form1data = $mform1->get_data()) {
        $importid = csv_import_reader::get_new_iid('uploadbadgeusers');
        $cir = new csv_import_reader($importid, 'uploadbadgeusers');
        $content = $mform1->get_file_content('badgecsv');
        $readcount = $cir->load_csv_content($content, $form1data->encoding, $form1data->delimiter_name);
        unset($content);
        if ($readcount === false) {
            print_error('csvfileerror', 'block_badgeawarder', $returnurl, $cir->get_error());
        } else if ($readcount == 0) {
            print_error('csvemptyfile', 'error', $returnurl, $cir->get_error());
        }
    } else {
        $PAGE->set_url('/blocks/badgeawarder/badgeawarder.php', array('courseid' => $course->id));
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('badgecsv', 'block_badgeawarder'));
        $PAGE->set_heading(get_string('badgecsv', 'block_badgeawarder'));

        $PAGE->set_title(get_string('badgecsv', 'block_badgeawarder'));
        $PAGE->navbar->add(get_string('uploadcsv', 'block_badgeawarder'), '', navigation_node::TYPE_CUSTOM);

        echo $OUTPUT->header(get_string('badgecsv', 'block_badgeawarder'));
        $samplecsv = html_writer::link(new moodle_url('/blocks/badgeawarder/badge_upload_sample.csv'),
            get_string('samplecsv', 'block_badgeawarder'));
        $icon = new help_icon('uploadbadgecsv', 'block_badgeawarder');
        echo $OUTPUT->heading(get_string('uploadbadgecsv', 'block_badgeawarder') . $OUTPUT->render($icon));

        $mform1->display();
        echo $OUTPUT->single_button(new moodle_url('/course/view.php', array('id' => $course->id)), get_string('back'), '');
        echo $OUTPUT->footer();
        die;
    }
} else {
    $cir = new csv_import_reader($importid, 'uploadbadgeusers');
}
// Data to set in the form.

$data = array('importid' => $importid, 'courseid' => $course->id);
if (!empty($form1data)) {
    $data['mode'] = $form1data->mode;
} else {
    $data['mode'] = $mode;
}

$mform2 = new block_badgeawarder_step2_form(null, array('data' => $data));

$returnurl2 = new moodle_url('/blocks/badgeawarder/badgeawarder.php', array('courseid' => $course->id));

// If a file has been uploaded, then process it.
if ($mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($returnurl2);
} else if ($form2data = $mform2->get_data()) {
    block_badgeawarder_page_header($course, $context);
    echo $OUTPUT->header(get_string('badgecsvpreview', 'block_badgeawarder'));
    $processor = new block_badgeawarder_processor($cir, $form2data, $courseid);
    $processor->execute(new block_badgeawarder_tracker());
    echo $OUTPUT->single_button($returnurl, get_string('returntocourse', 'block_badgeawarder'));
    echo $OUTPUT->footer();
    die;
} else {
    $processor = new block_badgeawarder_processor($cir, $data, $courseid);
    block_badgeawarder_page_header($course, $context);
    echo $OUTPUT->header(get_string('badgecsvpreview', 'block_badgeawarder'));
    $processor->preview($previewrows);
    if ($processor->nothingtodo) {
        echo html_writer::tag('div', get_string('nothingtodo', 'block_badgeawarder'), array('class' => 'alert alert-warning'));
        echo $OUTPUT->single_button($returnurl2, get_string('nothingtodobutton', 'block_badgeawarder'));
    } else {
        $mform2->display();
    }
}
