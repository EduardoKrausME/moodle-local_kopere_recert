<?php
// This file is part of Moodle - http://moodle.org/
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
 * version.php
 *
 * @package   recerttask_videoprogress
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Defines the subplugin version metadata.

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2026081301;
$plugin->release = '0.1.0';
$plugin->component = 'recerttask_videoprogress';
$plugin->requires = 2024042200;
$plugin->maturity = MATURITY_STABLE;

$plugin->dependencies = [
    'local_kopere_recert' => 2026081204,
];
