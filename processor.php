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
require_once($CFG->libdir . '/csvlib.class.php');


class block_badgeawarder_processor {

    /**
     * Award to new users only.
     */
    const MODE_CREATE_NEW = 1;

    /**
     * Award to all users, create non-existing users.
     */
    const MODE_CREATE_ALL = 2;

    /**
     * Award to existing users only.
     */
    const MODE_UPDATE_ONLY = 3;

    /**
     * During update, do not update anything... O_o Huh?!
     */
    const UPDATE_NOTHING = 0;

    /**
     * @bool true if there is really nothing to do.
     */
    public $nothingtodo;

    /** @var int processor mode. */
    protected $mode;

    /** @var string defaultcity for new user records. */
    protected $defaultcity;

    /** @var string defaultcountry for new user records. */
    protected $defaultcountry;

    /** @var string shortname of the course to be restored. */
    protected $courseid;

    /** @var string reset courses after processing them. */
    protected $reset;

    /** @var csv_import_reader */
    protected $cir;

    /** @var enrolmentinstance */
    protected $enrolinstance;

    /** @var manualenrolment */
    protected $manualenrolment;

    /** @var badges */
    protected $badges = array();

    /** @var array default values. */
    protected $defaults = array();

    /** @var array CSV columns. */
    protected $columns = array();

    /** @var array of errors where the key is the line number. */
    protected $errors = array();

    /** @var int line number. */
    protected $linenb = 0;

    /** @var bool whether the process has been started or not. */
    protected $processstarted = false;

    /**
     * @var array columns to display.
     */
    protected $filecolumns = array('firstname', 'lastname', 'email', 'badge');

    /**
     * Constructor
     *
     * @param csv_import_reader $cir import reader object
     * @param array $options options of the process
     * @param array $defaults default data value
     */
    public function __construct(csv_import_reader $cir, $options) {
        if (is_array($options)) {
            $arrayoptions = $options;
            $options = new Object();
            foreach ($arrayoptions as $key => $value) {
                $options->$key = $value;
            }
        }

        if (!isset($options->mode) || !in_array($options->mode, array(self::MODE_CREATE_NEW, self::MODE_CREATE_ALL,
        self::MODE_UPDATE_ONLY)) || !isset($options->courseid)) {
            throw new coding_exception('Invalid form info');
        }

        if (isset($options->city)) {
            $this->defaultcity = $options->city;
        }
        if (isset($options->country)) {
            $this->defaultcountry = $options->country;
        }

        $this->mode = $options->mode;
        $this->courseid = $options->courseid;

        $this->cir = $cir;
        $this->columns = $cir->get_columns();
        $this->validate();
        $this->reset();
    }

    /**
     * Execute the process.
     *
     * @param object $tracker the output tracker to use.
     * @return void
     */
    public function execute($tracker = null) {
        global $USER;

        if ($this->processstarted) {
            throw new coding_exception('Process has already been started');
        }
        $this->processstarted = true;

        if (empty($tracker)) {
            $tracker = new block_badgeawarder_tracker();
        }
        $tracker->start();

        $awardtotal = 0;
        $accountscreated = 0;
        $usersenrolled = 0;
        $errors = 0;

        // We will most certainly need extra time and memory to process big files.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);

        $enrolments = $this->get_enrolments();
        $this->get_enrolmentinstance();

        $existingemails = $this->get_existing_useremailaddresses();
        $existingusernames = $this->get_existing_usernames();

        // Include uploading user institution and department data.
        $this->institution = $USER->institution;
        $this->department = $USER->department;

        // Loop over the CSV lines.
        while ($line = $this->cir->next()) {
            $this->linenb++;

            $status = get_string('statusok', 'block_badgeawarder');

            $data = $this->parse_line($line);

            // Get or create user.
            if (array_key_exists($data['email'], $existingemails) || array_key_exists($data['email'], $existingusernames)) {
                if ($this->mode == self::MODE_CREATE_NEW) {
                    $result = false;
                    $status = get_string('statusskipexistinguser', 'block_badgeawarder');
                    $tracker->output($this->linenb, false, $status, $data);
                    continue;
                }
            } else {
                if (!validate_email($data['email'])) {
                    $result = false;
                    $status = get_string('statusskipinvalidemail', 'block_badgeawarder');
                    $tracker->output($this->linenb, false, $status, $data);
                    continue;
                }
                if ($this->mode == self::MODE_UPDATE_ONLY) {
                    $result = false;
                    $status = get_string('statusskipnewuser', 'block_badgeawarder');
                    $tracker->output($this->linenb, false, $status, $data);
                    continue;
                }
            }

            // Check badge.
            if ($badge = $this->get_badge($data['badge'])) {
                if ($badge->type != 2) {
                    $status = get_string('statuscoursebadgeonly', 'block_badgeawarder');
                    $tracker->output($this->linenb, false, $status, $data);
                    continue;
                } else if (!$this->check_badge_criteria($badge)) {
                    $status = get_string('statusbadgecriteriaerror', 'block_badgeawarder');
                    $tracker->output($this->linenb, false, $status, $data);
                    continue;
                }
            } else {
                $status = get_string('statusbadgenotexist', 'block_badgeawarder');
                $tracker->output($this->linenb, false, $status, $data);
                continue;
            }

            if ($user = $this->get_user($data)) {
                if ($user->new) {
                    $accountscreated++;
                }
            } else {
                $status = get_string('statusgetuserfailed', 'block_badgeawarder');
                $tracker->output($this->linenb, false, $status, $data);
                continue;
            }

            // Check enrolment and enrol if needed.
            if (!array_key_exists($user->id, $enrolments)) {
                $this->enrol_user($user);
                $usersenrolled++;
            }

            // Award badge.
            if ($badge->is_issued($user->id)) {
                $status = get_string('statusbadgealreadyawarded', 'block_badgeawarder');
                $tracker->output($this->linenb, false, $status, $data);
                continue;
            } else if ($badge->is_active()) {
                $badge->issue($user->id, true);
                $awardtotal++;
            } else {
                $status = get_string('statusbadgenotactive', 'block_badgeawarder');
                $tracker->output($this->linenb, false, $status, $data);
                continue;
            }

            // Send user email.
            $user->badgename = $data['badge'];
            if ($this->send_email($user)) {
                if ($user->new) {
                    $status = get_string('statusemailinvited', 'block_badgeawarder');
                } else {
                    $status = get_string('statusemailnotified', 'block_badgeawarder');
                }
            } else {
                $status = get_string('statusemailfailed', 'block_badgeawarder');
                $tracker->output($this->linenb, false, $status, $data);
                continue;
            }

            $tracker->output($this->linenb, true, $status, $data);

        }

        $tracker->finish();
        $tracker->results($awardtotal, $accountscreated, $usersenrolled, $errors);
    }

    private function get_badge($name) {
        global $DB;
        if (empty($name)) {
            return false;
        }
        if (isset($this->badges[$name])) {
            return $this->badges[$name];
        } else if ($badge = $DB->get_record('badge', array('name' => $name, 'courseid' => $this->courseid))) {
            $newbadge = new badge($badge->id);
            $this->badges[$name] = $newbadge;
            return $this->badges[$name];
        } else {
            return false;
        }
    }

    private function check_badge_criteria(badge $badge) {
        // Completion types: 0 -> overall, 1 -> activity, 2 -> manual.
        // 3 -> social, 4 -> course, 5 -> courseset, 6 -> profile.
        $activetypes = array();
        $manualactive = false;

        foreach ($badge->criteria as $type => $criteria) {
            // Role completion.
            if ($type != 2 && count($criteria->params) > 0) {
                return false;
            }
            // Manual completion.
            if ($type == 2 && count($criteria->params) > 0) {
                $manualactive = true;
            }
        }
        if ($manualactive) {
            return true;
        } else {
            return false;
        }
    }

    private function get_user($data) {
        global $CFG, $DB;
        if ($existinguser = $DB->get_record('user', array('email' => $data['email']))) {
            $existinguser->new = false;
            return $existinguser;
        }
        if ($existinguser = $DB->get_record('user', array('username' => $data['email']))) {
            $existinguser->new = false;
            return $existinguser;
        }

        if ($this->mode == self::MODE_CREATE_NEW || $this->mode == self::MODE_CREATE_ALL) {
            $user = new Object();
            $user->username = $data['email'];
            $user->email = $data['email'];
            $user->firstname = $data['firstname'];
            $user->lastname = $data['lastname'];
            $user->firstnamephonetic = '';
            $user->lastnamephonetic = '';
            $user->middlename = '';
            $user->alternatename = '';
            $user->idnumber = '';
            $user->timemodified = time();
            $user->timecreated  = time();
            $user->firstaccess  = '';
            $user->lastaccess  = '';
            $user->lastlogin  = '';
            $user->currentlogin = '';
            $user->city = $this->defaultcity;
            $user->country = $this->defaultcountry;
            $user->mnethostid   = $CFG->mnet_localhost_id;
            $user->institution = $this->institution;
            $user->department = $this->department;
            $user->auth = 'manual';
            $user->policyagreed = 1;
            $user->picture = 0;
            $user->confirmed = 1;
            $user->deleted = 0;
            $user->trackforums = 0;
            $user->secret = random_string(15);
            $user->newpassword = generate_password();
            $user->password = hash_internal_user_password($user->newpassword, true);
            if ($user->id = $DB->insert_record('user', $user)) {
                $user->new = true;
                return $user;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function get_enrolments() {
        $context = context_course::instance($this->courseid);
        return get_enrolled_users($context);
    }

    private function get_enrolmentinstance() {
        global $DB;
        if (enrol_is_enabled('manual')) {
            $this->manualenrolment = enrol_get_plugin('manual');
        } else {
            print_error("Manual enrolments disabled");
        }
        $this->enrolinstance = $DB->get_record('enrol', array('courseid' => $this->courseid, 'enrol' => 'manual'), '*', MUST_EXIST);
    }

    private function send_email($user) {
        global $CFG;
        $user->siteurl = $CFG->wwwroot;
        $supportuser = core_user::get_support_user();

        $emailawardsubject = get_string('emailawardsubject', 'block_badgeawarder');
        if ($user->new) {
            $emailawardtexthtml = get_string('emailawardtextnew', 'block_badgeawarder', $user);
        } else {
            $emailawardtexthtml = get_string('emailawardtextexisting', 'block_badgeawarder', $user);
        }

        $emailawardtext = strip_tags($emailawardtexthtml);

        return email_to_user($user, $supportuser, $emailawardsubject, $emailawardtext, $emailawardtexthtml);
    }


    private function enrol_user($user) {
        $this->manualenrolment->enrol_user($this->enrolinstance, $user->id, 5);
    }


    /**
     * Return the errors.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }


    /**
     * Log errors on the current line.
     *
     * @param array $errors array of errors
     * @return void
     */
    protected function log_error($errors) {
        if (empty($errors)) {
            return;
        }

        foreach ($errors as $code => $langstring) {
            if (!isset($this->errors[$this->linenb])) {
                $this->errors[$this->linenb] = array();
            }
            $this->errors[$this->linenb][$code] = $langstring;
        }
    }

    /**
     * Parse a line to return an array(column => value)
     *
     * @param array $line returned by csv_import_reader
     * @return array
     */
    protected function parse_line($line) {
        $data = array();
        foreach ($line as $keynum => $value) {
            if (!isset($this->columns[$keynum])) {
                // This should not happen.
                continue;
            }

            $key = $this->columns[$keynum];
            $data[$key] = $value;
        }
        return $data;
    }

    protected function get_existing_useremailaddresses() {
        global $DB;
        $sql = "SELECT DISTINCT(email) FROM {user} where DELETED = 0 ORDER BY email";
        return (array) $DB->get_records_sql($sql);
    }

    protected function get_existing_usernames() {
        global $DB;
        $sql = "SELECT DISTINCT(username) FROM {user} where DELETED = 0 ORDER BY username";
        return (array) $DB->get_records_sql($sql);
    }

    protected function check_required_fields($data) {
        $checkedfields = 0;

        foreach ($data as $key => $field) {
            if (in_array($key, $this->filecolumns) && !empty($field)) {
                $checkedfields++;
            }
        }
        if (count($this->filecolumns) == $checkedfields) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return a preview of the import.
     *
     * This only returns passed data, along with the errors.
     *
     * @param integer $rows number of rows to preview.
     * @param object $tracker the output tracker to use.
     * @return array of preview data.
     */
    public function preview($rows = 10) {
        global $DB;

        $choices = array(
        self::MODE_CREATE_NEW => get_string('awardnew', 'block_badgeawarder'),
        self::MODE_CREATE_ALL => get_string('awardall', 'block_badgeawarder'),
        self::MODE_UPDATE_ONLY => get_string('awardexisting', 'block_badgeawarder')
        );

        echo html_writer::tag('h3', get_string('preview', 'block_badgeawarder') . ' ' . $choices[$this->mode]);

        $tracker = new block_badgeawarder_tracker();

        $tracker->start();

        if ($this->processstarted) {
            throw new coding_exception('Process has already been started');
        }

        $this->processstarted = true;

        // We might need extra time and memory depending on the number of rows to preview.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_EXTRA);

        $existingemails = $this->get_existing_useremailaddresses();
        $existingusernames = $this->get_existing_usernames();

        $nothingtodo = true;
        // Loop over the CSV lines.
        while (($line = $this->cir->next()) && $rows > $this->linenb) {
            $this->linenb++;
            $data = $this->parse_line($line);

            $result = true;
            $status = array();

            if (!$this->check_required_fields($data)) {
                $result = false;
                $status[] = get_string('statusmissingfields', 'block_badgeawarder');
            }

            if (!$badge = $this->get_badge($data['badge'])) {
                $result = false;
                $status[] = get_string('statusbadgenotexist', 'block_badgeawarder');
            } else if (!$badge->is_active()) {
                $result = false;
                $status[] = get_string('statusbadgenotactive', 'block_badgeawarder');
            } else if ($badge->type != 2) {
                $result = false;
                $status[] = get_string('statuscoursebadgeonly', 'block_badgeawarder');
            } else if (!$this->check_badge_criteria($badge)) {
                $result = false;
                $status[] = get_string('statusbadgecriteriaerror', 'block_badgeawarder');
            }

            if (array_key_exists($data['email'], $existingemails) || array_key_exists($data['email'], $existingusernames) ) {
                if ($this->mode == self::MODE_CREATE_NEW) {
                    $result = false;
                    $status[] = get_string('statusskipexistinguser', 'block_badgeawarder');
                }
            } else {
                if ($this->mode == self::MODE_UPDATE_ONLY) {
                    $result = false;
                    $status[] = get_string('statusskipnewuser', 'block_badgeawarder');
                }
                if (!validate_email($data['email'])) {
                    $result = false;
                    $status[] = get_string('statusskipinvalidemail', 'block_badgeawarder');
                }
            }
            if ($result) {
                $nothingtodo = false;
            }
            $tracker->output($this->linenb, $result, $status, $data);
        }

        // Check if there are more records then in preview.
        if (!$nothingtodo) {
            $this->nothingtodo = false;
        } else if ($this->cir->next()) {
            $this->nothingtodo = false;
        } else {
            $this->nothingtodo = true;
        }
        $tracker->finish();
    }

    /**
     * Reset the current process.
     *
     * @return void.
     */
    public function reset() {
        $this->processstarted = false;
        $this->linenb = 0;
        $this->cir->init();
        $this->errors = array();
    }

    /**
     * Validation.
     *
     * @return void
     */
    protected function validate() {
        global $COURSE;
        foreach ($this->filecolumns as $requiredcolumn) {
            if (!in_array($requiredcolumn, $this->columns)) {
                $returnlink = new moodle_url('/course/view.php', array('id' => $COURSE->id));
                throw new moodle_exception('csvloaderror', 'error',
                    $returnlink, get_string('csvformaterror', 'block_badgeawarder'), '');
            }
        }
        if (empty($this->columns)) {
            throw new moodle_exception('cannotreadtmpfile', 'error');
        } else if (count($this->columns) < 4) {
            throw new moodle_exception('csvfewcolumns', 'error');
        }
    }
}
