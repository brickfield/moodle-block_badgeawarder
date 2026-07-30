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

namespace block_badgeawarder\privacy;

use core_privacy\local\metadata\collection;

/**
 * Tests for block_badgeawarder's privacy provider.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_badgeawarder\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    public function test_get_metadata_registers_expected_subsystem_links(): void {
        $this->resetAfterTest();

        $collection = new collection('block_badgeawarder');
        $result = provider::get_metadata($collection);
        $items = $result->get_collection();

        $this->assertCount(3, $items);

        $names = array_map(fn ($item) => $item->get_name(), $items);
        $this->assertEqualsCanonicalizing(['core_user', 'core_enrol', 'core_badges'], $names);

        $summaries = array_map(fn ($item) => $item->get_summary(), $items);
        $this->assertEqualsCanonicalizing([
            'privacy:metadata:coreuser',
            'privacy:metadata:coreenrol',
            'privacy:metadata:corebadges',
        ], $summaries);
    }
}
