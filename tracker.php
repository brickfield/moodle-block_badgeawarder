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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/weblib.php');

/**
 * File containing the results tracker class, used to render the CSV upload's progress table.
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_badgeawarder_tracker {
    /**
     * @var array columns to display.
     */
    protected $columns = ['firstname', 'lastname', 'email', 'badge'];

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
     * @param int $awardtotal total courses.
     * @param int $accountscreated count of courses created.
     * @param int $usersenrolled count of users enrolled.
     * @param int $errors count of errors.
     * @return void
     */
    public function results($awardtotal, $accountscreated, $usersenrolled, $errors) {

        $message = [
            get_string('awardtotal', 'block_badgeawarder', $awardtotal),
            get_string('accountscreated', 'block_badgeawarder', $accountscreated),
            get_string('usersenrolled', 'block_badgeawarder', $usersenrolled),
            get_string('awarderrors', 'block_badgeawarder', $errors),
        ];

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
     * @param array|string $status status message(s) — either one string or an array of strings
     *                             to join with a line break.
     * @param array $data the parsed CSV row (firstname, lastname, email, badge) to display.
     * @return void
     */
    public function output($line, $outcome, $status, $data) {
        global $OUTPUT;

        $ci = 0;
        $this->rownb++;
        if (is_array($status)) {
            $status = count($status) > 1 ? html_writer::alist($status) : reset($status);
        }
        if ($outcome) {
            $outcome = $OUTPUT->pix_icon('i/valid', get_string('iconsuccess', 'block_badgeawarder'));
        } else {
            $outcome = $OUTPUT->pix_icon('i/invalid', get_string('iconerror', 'block_badgeawarder'));
        }
        echo html_writer::start_tag('tr', ['class' => 'r' . $this->rownb % 2]);
        echo html_writer::tag('td', $line, ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', $outcome, ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', isset($data['firstname']) ? s($data['firstname']) : '', ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', isset($data['lastname']) ? s($data['lastname']) : '', ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', isset($data['email']) ? s($data['email']) : '', ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', isset($data['badge']) ? s($data['badge']) : '', ['class' => 'c' . $ci++]);
        echo html_writer::tag('td', $status, ['class' => 'c' . $ci++]);
        echo html_writer::end_tag('tr');
    }

    /**
     * Start the output.
     *
     * @return void
     */
    public function start() {
        $ci = 0;
        echo html_writer::start_tag('table', ['class' => 'generaltable boxaligncenter flexible-wrap']);
        echo html_writer::tag('caption', get_string('awardresulttablesummary', 'block_badgeawarder'));
        echo html_writer::start_tag('tr', ['class' => 'heading r' . $this->rownb]);
        echo html_writer::tag(
            'th',
            get_string('csvline', 'block_badgeawarder'),
            ['class' => 'c' . $ci++, 'scope' => 'col']
        );
        echo html_writer::tag('th', get_string('result', 'block_badgeawarder'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('firstname'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('lastname'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('email'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('badge', 'block_badgeawarder'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::tag('th', get_string('status'), ['class' => 'c' . $ci++, 'scope' => 'col']);
        echo html_writer::end_tag('tr');
    }
}
