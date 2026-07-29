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
require_once($CFG->libdir . '/csvlib.class.php');
require_once($CFG->dirroot . '/badges/lib/awardlib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * File containing processor class.
 *
 * @package    block_badgeawarder
 * @copyright  2013 Learning Technology Services, www.lts.ie - Lead Developer: Bas Brands
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
     * @var bool true if there is really nothing to do.
     */
    public $nothingtodo;

    /** @var int processor mode. */
    protected $mode;

    /** @var string defaultcity for new user records. */
    protected $defaultcity;

    /** @var string defaultcountry for new user records. */
    protected $defaultcountry;

    /** @var string institution of the user running the import, copied onto newly created accounts. */
    protected $institution;

    /** @var string department of the user running the import, copied onto newly created accounts. */
    protected $department;

    /** @var string shortname of the course to be restored. */
    protected $courseid;

    /** @var csv_import_reader */
    protected $cir;

    /** @var enrolmentinstance */
    protected $enrolinstance;

    /** @var manualenrolment */
    protected $manualenrolment;

    /** @var badges */
    protected $badges = [];

    /** @var array CSV columns. */
    protected $columns = [];

    /** @var int line number. */
    protected $linenb = 0;

    /** @var bool whether the process has been started or not. */
    protected $processstarted = false;

    /**
     * @var array columns to display.
     */
    protected $filecolumns = ['firstname', 'lastname', 'email', 'badge'];

    /**
     * Constructor
     *
     * @param csv_import_reader $cir import reader object
     * @param array|stdClass $options options of the process (mode, courseid, and optionally
     *                                city/country).
     */
    public function __construct(csv_import_reader $cir, $options) {
        if (is_array($options)) {
            $arrayoptions = $options;
            $options = new stdClass();
            foreach ($arrayoptions as $key => $value) {
                $options->$key = $value;
            }
        }

        if (
            !isset($options->mode) || !in_array($options->mode, [self::MODE_CREATE_NEW, self::MODE_CREATE_ALL,
            self::MODE_UPDATE_ONLY]) || !isset($options->courseid)
        ) {
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

        $coursecontext = context_course::instance($this->courseid);

        require_capability('enrol/manual:enrol', $coursecontext);
        require_capability('moodle/badges:awardbadge', $coursecontext);

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

            $data = $this->parse_line($line);

            $skipstatus = $this->resolve_recipient_skip_status($data, $existingemails, $existingusernames);
            if ($skipstatus !== null) {
                $tracker->output($this->linenb, false, $skipstatus, $data);
                continue;
            }

            [$badge, $skipstatus] = $this->resolve_award_badge($data);
            if ($skipstatus !== null) {
                $tracker->output($this->linenb, false, $skipstatus, $data);
                continue;
            }

            if ($user = $this->get_user($data)) {
                $accountscreated += (int) $user->new;
            } else {
                $tracker->output($this->linenb, false, get_string('statusgetuserfailed', 'block_badgeawarder'), $data);
                continue;
            }

            [$status, $outcome, $enrolled, $awarded] = $this->finalize_award($user, $badge, $data, $enrolments);
            $usersenrolled += (int) $enrolled;
            $awardtotal += (int) $awarded;
            $tracker->output($this->linenb, $outcome, $status, $data);
        }

        $tracker->finish();
        $tracker->results($awardtotal, $accountscreated, $usersenrolled, $errors);
    }

    /**
     * Enrols an already-resolved recipient if needed, awards the badge, and emails them,
     * returning the outcome to report for the row.
     *
     * @param stdClass $user the resolved (existing or newly created) recipient.
     * @param badge $badge the badge to award.
     * @param array $data the parsed CSV row.
     * @param array $enrolments users already enrolled in the course, keyed by user id.
     * @return array [string $status, bool $outcome, bool $enrolled, bool $awarded] the tracker
     *               status and outcome to report, whether the user was newly enrolled, and
     *               whether the badge was newly issued.
     */
    private function finalize_award($user, badge $badge, $data, array $enrolments) {
        global $DB, $USER;

        $enrolled = false;
        if (!array_key_exists($user->id, $enrolments)) {
            $this->enrol_user($user);
            $enrolled = true;
        }

        if ($badge->is_issued($user->id)) {
            return [get_string('statusbadgealreadyawarded', 'block_badgeawarder'), false, $enrolled, false];
        }
        if (!$badge->is_active()) {
            return [get_string('statusbadgenotactive', 'block_badgeawarder'), false, $enrolled, false];
        }

        $badge->issue($user->id, true);
        $teacher = $DB->get_record('role', ['archetype' => 'teacher']);
        process_manual_award($user->id, $USER->id, $teacher->id, $badge->id);

        $user->badgename = $data['badge'];
        if (!$this->send_email($user)) {
            return [get_string('statusemailfailed', 'block_badgeawarder'), false, $enrolled, true];
        }

        $status = $user->new
            ? get_string('statusemailinvited', 'block_badgeawarder')
            : get_string('statusemailnotified', 'block_badgeawarder');
        return [$status, true, $enrolled, true];
    }

    /**
     * Determines whether a CSV row's recipient should be skipped, based on whether the email
     * matches an existing account, the email's validity, and the processor's mode.
     *
     * @param array $data the parsed CSV row.
     * @param array $existingemails known user emails, keyed by email.
     * @param array $existingusernames known usernames, keyed by username.
     * @return string|null a status string to report and skip the row, or null to proceed.
     */
    private function resolve_recipient_skip_status($data, $existingemails, $existingusernames) {
        if (array_key_exists($data['email'], $existingemails) || array_key_exists($data['email'], $existingusernames)) {
            if ($this->mode == self::MODE_CREATE_NEW) {
                return get_string('statusskipexistinguser', 'block_badgeawarder');
            }
            return null;
        }

        if (!validate_email($data['email'])) {
            return get_string('statusskipinvalidemail', 'block_badgeawarder');
        }
        if ($this->mode == self::MODE_UPDATE_ONLY) {
            return get_string('statusskipnewuser', 'block_badgeawarder');
        }
        return null;
    }

    /**
     * Looks up a CSV row's badge and checks it is a course badge with manual-award criteria.
     *
     * @param array $data the parsed CSV row.
     * @return array [badge, status] the badge and null on success, or null and a status string
     *               to report and skip the row.
     */
    private function resolve_award_badge($data) {
        if (!$badge = $this->get_badge($data['badge'])) {
            return [null, get_string('statusbadgenotexist', 'block_badgeawarder')];
        }
        if ($badge->type != 2) {
            return [null, get_string('statuscoursebadgeonly', 'block_badgeawarder')];
        }
        if (!$this->check_badge_criteria($badge)) {
            return [null, get_string('statusbadgecriteriaerror', 'block_badgeawarder')];
        }
        return [$badge, null];
    }

    /**
     * Returns a badge object from a name.
     *
     * @param string $name the name of the badge.
     * @return object|bool
     */
    private function get_badge($name) {
        global $DB;
        if (empty($name)) {
            return false;
        }
        if (isset($this->badges[$name])) {
            return $this->badges[$name];
        } else if ($badge = $DB->get_record('badge', ['name' => $name, 'courseid' => $this->courseid])) {
            $newbadge = new badge($badge->id);
            $this->badges[$name] = $newbadge;
            return $this->badges[$name];
        } else {
            return false;
        }
    }

    /**
     * Checks the criteria of a badge.
     *
     * @param badge $badge the badge object.
     * @return bool
     */
    private function check_badge_criteria(badge $badge) {
        // Completion types: 0 -> overall, 1 -> activity, 2 -> manual.
        // 3 -> social, 4 -> course, 5 -> courseset, 6 -> profile.
        $manualactive = false;

        foreach ($badge->criteria as $type => $criteria) {
            // Role completion.
            if ($type != 2 && $criteria->params && count($criteria->params) > 0) {
                return false;
            }
            // Manual completion.
            if ($type == 2 && $criteria->params && count($criteria->params) > 0) {
                $manualactive = true;
            }
        }
        if ($manualactive) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns or creates a user depending on the mode.
     *
     * @param array $data The parsed CSV row (firstname, lastname, email, badge).
     * @return object|bool
     */
    private function get_user($data) {
        global $CFG, $DB;
        if ($existinguser = $DB->get_record('user', ['email' => $data['email']])) {
            $existinguser->new = false;
            return $existinguser;
        }
        if ($existinguser = $DB->get_record('user', ['username' => $data['email']])) {
            $existinguser->new = false;
            return $existinguser;
        }

        if ($this->mode == self::MODE_CREATE_NEW || $this->mode == self::MODE_CREATE_ALL) {
            $user = new stdClass();
            $user->username = strtolower($data['email']);
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
            $user->city = $this->defaultcity;
            $user->country = $this->defaultcountry == "0" ? '' : $this->defaultcountry; // 0 isn't a valid country option.
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
            $user->password = $user->newpassword;

            try {
                $user->id = user_create_user($user, true, true);
            } catch (moodle_exception $e) {
                return false;
            }

            $user->new = true;
            return $user;
        } else {
            return false;
        }
    }

    /**
     * Retrieves all enrollments for the current course
     *
     * @return array
     */
    private function get_enrolments() {
        $context = context_course::instance($this->courseid);
        return get_enrolled_users($context);
    }

    /**
     * Retrieves an enrolment instance for the current course.
     *
     * @return void
     * @throws moodle_exception If manual enrolment is disabled on this site.
     */
    private function get_enrolmentinstance() {
        global $DB;
        if (enrol_is_enabled('manual')) {
            $this->manualenrolment = enrol_get_plugin('manual');
        } else {
            throw new moodle_exception('statusmanualenroldisabled', 'block_badgeawarder');
        }
        $this->enrolinstance = $DB->get_record('enrol', ['courseid' => $this->courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
    }

    /**
     * Sends an email to the user with the awarded badge details.
     *
     * @param object $user The user object to email (with badgename/siteurl already set).
     * @return bool
     */
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


    /**
     * Enrols a user in a course.
     *
     * @param object $user The user object to enrol.
     * @return void
     */
    private function enrol_user($user) {
        $this->manualenrolment->enrol_user($this->enrolinstance, $user->id, 5);
    }


    /**
     * Parse a line to return an array(column => value)
     *
     * @param array $line returned by csv_import_reader
     * @return array
     */
    protected function parse_line($line) {
        $data = [];
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

    /**
     * Retrieves an array of all unique user email addresses.
     *
     * @return array
     */
    protected function get_existing_useremailaddresses() {
        global $DB;
        $sql = "SELECT DISTINCT(email) FROM {user} where DELETED = 0 ORDER BY email";
        return (array) $DB->get_records_sql($sql);
    }

    /**
     * Retrieves an array of all unique usernames.
     *
     * @return array
     */
    protected function get_existing_usernames() {
        global $DB;
        $sql = "SELECT DISTINCT(username) FROM {user} where DELETED = 0 ORDER BY username";
        return (array) $DB->get_records_sql($sql);
    }

    /**
     * Verifies that filecolumns has all the required fields.
     *
     * @param array $data The parsed CSV row to check.
     * @return bool
     */
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
     * Previews the import by echoing a results table (via the tracker) for up to $rows CSV
     * lines, without processing them.
     *
     * Sets $this->nothingtodo to indicate whether any row in the preview is eligible for award.
     *
     * @param int $rows number of rows to preview.
     * @return void
     */
    public function preview($rows = 10) {
        $choices = [
        self::MODE_CREATE_NEW => get_string('awardnew', 'block_badgeawarder'),
        self::MODE_CREATE_ALL => get_string('awardall', 'block_badgeawarder'),
        self::MODE_UPDATE_ONLY => get_string('awardexisting', 'block_badgeawarder'),
        ];

        echo html_writer::tag('h2', get_string('preview', 'block_badgeawarder') . ' ' . $choices[$this->mode]);

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
        // Loop over the CSV lines. The row-count check must come first: it short-circuits the
        // && before cir->next() is called once the preview window is full, so the "peek ahead"
        // check below sees the very next unprocessed row rather than one it silently consumed.
        while ($rows > $this->linenb && ($line = $this->cir->next())) {
            $this->linenb++;
            $data = $this->parse_line($line);

            $status = $this->resolve_preview_status($data, $existingemails, $existingusernames);
            $result = empty($status);
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
     * Checks a previewed row's badge exists, is active, is a course badge, and has manual-award
     * criteria, without performing any side effects.
     *
     * @param array $data the parsed CSV row.
     * @return string|null a status string explaining why the badge is ineligible, or null if fine.
     */
    private function preview_badge_status($data) {
        if (!$badge = $this->get_badge($data['badge'])) {
            return get_string('statusbadgenotexist', 'block_badgeawarder');
        }
        if (!$badge->is_active()) {
            return get_string('statusbadgenotactive', 'block_badgeawarder');
        }
        if ($badge->type != 2) {
            return get_string('statuscoursebadgeonly', 'block_badgeawarder');
        }
        if (!$this->check_badge_criteria($badge)) {
            return get_string('statusbadgecriteriaerror', 'block_badgeawarder');
        }
        return null;
    }

    /**
     * Evaluates a single previewed CSV row against required fields, badge validity, and the
     * processor's existing-user/mode rules, without performing any side effects.
     *
     * @param array $data the parsed CSV row.
     * @param array $existingemails known user emails, keyed by email.
     * @param array $existingusernames known usernames, keyed by username.
     * @return array status messages explaining why the row is ineligible; empty if eligible.
     */
    private function resolve_preview_status($data, $existingemails, $existingusernames) {
        $status = [];

        if (!$this->check_required_fields($data)) {
            $status[] = get_string('statusmissingfields', 'block_badgeawarder');
        }

        if ($badgestatus = $this->preview_badge_status($data)) {
            $status[] = $badgestatus;
        }

        if (array_key_exists($data['email'], $existingemails) || array_key_exists($data['email'], $existingusernames)) {
            if ($this->mode == self::MODE_CREATE_NEW) {
                $status[] = get_string('statusskipexistinguser', 'block_badgeawarder');
            }
        } else {
            if ($this->mode == self::MODE_UPDATE_ONLY) {
                $status[] = get_string('statusskipnewuser', 'block_badgeawarder');
            }
            if (!validate_email($data['email'])) {
                $status[] = get_string('statusskipinvalidemail', 'block_badgeawarder');
            }
        }

        return $status;
    }

    /**
     * Reset the current process.
     *
     * @return void
     */
    public function reset() {
        $this->processstarted = false;
        $this->linenb = 0;
        $this->cir->init();
    }

    /**
     * Validates that the CSV file has all four required columns (firstname, lastname, email,
     * badge) and isn't empty.
     *
     * @return void
     */
    protected function validate() {
        global $COURSE;
        if (empty($this->columns)) {
            throw new moodle_exception('cannotreadtmpfile', 'error');
        }
        foreach ($this->filecolumns as $requiredcolumn) {
            if (!in_array($requiredcolumn, $this->columns)) {
                $returnlink = new moodle_url('/course/view.php', ['id' => $COURSE->id]);
                throw new moodle_exception(
                    'csvloaderror',
                    'error',
                    $returnlink,
                    get_string('csvformaterror', 'block_badgeawarder'),
                    ''
                );
            }
        }
        if (count($this->columns) < 4) {
            throw new moodle_exception('csvfewcolumns', 'error');
        }
    }
}
