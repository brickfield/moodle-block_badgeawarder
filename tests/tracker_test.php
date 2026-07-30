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

namespace block_badgeawarder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/badgeawarder/tracker.php');

/**
 * Tests for block_badgeawarder_tracker's output escaping.
 *
 * Regression test for the bf-security XSS fix, which wrapped the four CSV-derived fields in
 * s() before passing them to html_writer::tag() — see docs/state/ai-session-notes.md.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_badgeawarder_tracker
 */
final class tracker_test extends \advanced_testcase {
    /**
     * Tests that output() escapes a malicious value in the given field.
     *
     * @param string $field The CSV field (firstname, lastname, email, or badge) to inject the payload into.
     * @dataProvider malicious_field_provider
     */
    public function test_output_escapes_csv_supplied_values(string $field): void {
        $this->resetAfterTest();
        $payload = '<script>alert(1)</script>';
        $data = ['firstname' => 'Jane', 'lastname' => 'Doe', 'email' => 'jane@example.com', 'badge' => 'Test badge'];
        $data[$field] = $payload;

        $tracker = new \block_badgeawarder_tracker();
        ob_start();
        $tracker->output(1, true, get_string('statusok', 'block_badgeawarder'), $data);
        $html = ob_get_clean();

        $this->assertStringNotContainsString($payload, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Data provider of CSV field names to inject a malicious payload into.
     *
     * @return array
     */
    public static function malicious_field_provider(): array {
        return [
            'firstname' => ['firstname'],
            'lastname' => ['lastname'],
            'email' => ['email'],
            'badge' => ['badge'],
        ];
    }
}
