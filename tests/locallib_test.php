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
require_once($CFG->dirroot . '/blocks/badgeawarder/locallib.php');
require_once($CFG->dirroot . '/blocks/badgeawarder/processor.php');

/**
 * Tests for block_badgeawarder_resolve_mode().
 *
 * This function was added specifically to stop a client-supplied 'mode' form value from
 * bypassing the admin-configured default when extended upload options are hidden — see the
 * bf-security fix recorded in docs/state/ai-session-notes.md.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::block_badgeawarder_resolve_mode
 */
final class locallib_test extends \advanced_testcase {
    public function test_resolve_mode_returns_client_mode_when_extended_options_enabled(): void {
        $this->resetAfterTest();
        set_config('showextendedoption', 1, 'block_badgeawarder');

        $result = block_badgeawarder_resolve_mode(\block_badgeawarder_processor::MODE_UPDATE_ONLY);

        $this->assertSame(\block_badgeawarder_processor::MODE_UPDATE_ONLY, $result);
    }

    public function test_resolve_mode_ignores_client_mode_when_extended_options_disabled(): void {
        $this->resetAfterTest();
        set_config('showextendedoption', 0, 'block_badgeawarder');
        set_config('defaultuploadtype', \block_badgeawarder_processor::MODE_UPDATE_ONLY, 'block_badgeawarder');

        // The client tries to smuggle a more privileged mode value past the admin default.
        $result = block_badgeawarder_resolve_mode(\block_badgeawarder_processor::MODE_CREATE_ALL);

        $this->assertSame(\block_badgeawarder_processor::MODE_UPDATE_ONLY, $result);
    }

    public function test_resolve_mode_falls_back_to_create_all_with_no_default_configured(): void {
        $this->resetAfterTest();
        set_config('showextendedoption', 0, 'block_badgeawarder');
        // Deliberately leave defaultuploadtype unset.

        $result = block_badgeawarder_resolve_mode(\block_badgeawarder_processor::MODE_UPDATE_ONLY);

        $this->assertSame(\block_badgeawarder_processor::MODE_CREATE_ALL, $result);
    }
}
