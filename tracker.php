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
 * File containing processor class.
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/weblib.php');

class block_badgeawarder_tracker {


    /**
     * @var array columns to display.
     */
    protected $columns = array('firstname', 'lastname', 'email', 'badge');

    /**
     * @var int row number.
     */
    protected $rownb = 0;

    /**
     * @var int chosen output mode.
     */
    protected $outputmode;

    /**
     * @var object output buffer.
     */
    protected $buffer;

    /**
     * Constructor.
     *
     * @param int $outputmode desired output mode.
     */
    public function __construct() {
    }

    /**
     * Finish the output.
     *
     * @return void
     */
    public function finish() {
        echo html_writer::end_tag('table');
    }

    /**
     * Output the results.
     *
     * @param int $total total courses.
     * @param int $created count of courses created.
     * @param int $updated count of courses updated.
     * @param int $deleted count of courses deleted.
     * @param int $errors count of errors.
     * @return void
     */
    public function results($awardtotal, $accountscreated, $usersenrolled, $errors) {

        $message = array(
            get_string('awardtotal', 'block_badgeawarder', $awardtotal),
            get_string('accountscreated', 'block_badgeawarder', $accountscreated),
            get_string('usersenrolled', 'block_badgeawarder', $usersenrolled),
            get_string('awarderrors', 'block_badgeawarder', $errors)
        );

        $buffer = new progress_trace_buffer(new html_list_progress_trace());
        foreach ($message as $msg) {
            $buffer->output($msg);
        }
        $buffer->finished();

    }

    /**
     * Output one more line.
     *
     * @param int $line line number.
     * @param bool $outcome success or not?
     * @param array $status array of statuses.
     * @param array $data extra data to display.
     * @return void
     */
    public function output($line, $outcome, $status, $data) {
        global $OUTPUT;

        $ci = 0;
        $this->rownb++;
        if (is_array($status)) {
            $status = implode(html_writer::empty_tag('br'), $status);
        }
        if ($outcome) {
            $outcome = $OUTPUT->pix_icon('i/valid', '');
        } else {
            $outcome = $OUTPUT->pix_icon('i/invalid', '');
        }
        echo html_writer::start_tag('tr', array('class' => 'r' . $this->rownb % 2));
        echo html_writer::tag('td', $line, array('class' => 'c' . $ci++));
        echo html_writer::tag('td', $outcome, array('class' => 'c' . $ci++));
        echo html_writer::tag('td', isset($data['firstname']) ? $data['firstname'] : '', array('class' => 'c' . $ci++));
        echo html_writer::tag('td', isset($data['lastname']) ? $data['lastname'] : '', array('class' => 'c' . $ci++));
        echo html_writer::tag('td', isset($data['email']) ? $data['email'] : '', array('class' => 'c' . $ci++));
        echo html_writer::tag('td', isset($data['badge']) ? $data['badge'] : '', array('class' => 'c' . $ci++));
        echo html_writer::tag('td', $status, array('class' => 'c' . $ci++));
        echo html_writer::end_tag('tr');

    }

    /**
     * Start the output.
     *
     * @return void
     */
    public function start() {
        $ci = 0;
        echo html_writer::start_tag('table', array('class' => 'generaltable boxaligncenter flexible-wrap',
                'summary' => get_string('awardresult', 'block_badgeawarder')));
        echo html_writer::start_tag('tr', array('class' => 'heading r' . $this->rownb));
        echo html_writer::tag('th', get_string('csvline', 'block_badgeawarder'),
        array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('result', 'block_badgeawarder'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('firstname'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('lastname'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('email'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('badge', 'block_badgeawarder'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::tag('th', get_string('status'), array('class' => 'c' . $ci++, 'scope' => 'col'));
        echo html_writer::end_tag('tr');
    }

}
