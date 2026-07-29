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

/**
 * A test double for block_badgeawarder_tracker that records calls instead of rendering HTML.
 *
 * block_badgeawarder_processor::execute()/preview() accept any object exposing the same
 * public methods as block_badgeawarder_tracker (the parameter carries no type hint), so tests
 * can inspect exactly what was reported per CSV line without depending on HTML output.
 *
 * @package    block_badgeawarder
 * @copyright  2026 Brickfield Education Labs Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_tracker {
    /** @var array Rows recorded via output(), one entry per call. */
    public array $rows = [];

    /** @var array|null Totals recorded via results(), or null if results() was never called. */
    public ?array $totals = null;

    /**
     * Records that output rendering started. No-op for this test double.
     *
     * @return void
     */
    public function start(): void {
    }

    /**
     * Records that output rendering finished. No-op for this test double.
     *
     * @return void
     */
    public function finish(): void {
    }

    /**
     * Records the final totals reported by the processor.
     *
     * @param int $awardtotal
     * @param int $accountscreated
     * @param int $usersenrolled
     * @param int $errors
     * @return void
     */
    public function results($awardtotal, $accountscreated, $usersenrolled, $errors): void {
        $this->totals = compact('awardtotal', 'accountscreated', 'usersenrolled', 'errors');
    }

    /**
     * Records one CSV line's outcome.
     *
     * @param int $line
     * @param bool $outcome
     * @param array|string $status
     * @param array $data
     * @return void
     */
    public function output($line, $outcome, $status, $data): void {
        $this->rows[] = ['line' => $line, 'outcome' => $outcome, 'status' => $status, 'data' => $data];
    }

    /**
     * Returns the status recorded for a given CSV line number, or null if none was recorded.
     *
     * @param int $line
     * @return array|string|null
     */
    public function status_for(int $line) {
        foreach ($this->rows as $row) {
            if ($row['line'] == $line) {
                return $row['status'];
            }
        }
        return null;
    }
}
