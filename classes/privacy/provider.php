<?php
// This file is part of the Block Badgeawarder plugin
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
 * Privacy API implementation for the Block Badgeawarder plugin.
 * @package    block_badgeawarder
 * @category   privacy
 * @copyright  2018 Karen Holland <karen@lts.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider {
    /**
     * Returns metadata about the personal data flows this plugin causes, even though it stores
     * nothing in tables of its own.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_subsystem_link(
            'core_user',
            [],
            'privacy:metadata:coreuser'
        );

        $collection->add_subsystem_link(
            'core_enrol',
            [],
            'privacy:metadata:coreenrol'
        );

        $collection->add_subsystem_link(
            'core_badges',
            [],
            'privacy:metadata:corebadges'
        );

        return $collection;
    }
}
