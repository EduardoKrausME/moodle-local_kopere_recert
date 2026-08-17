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
 * History renderer service.
 *
 * @package   local_kopere_recert
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_recert\history;

use core\output\mustache_engine;
use local_kopere_recert\task\task_context;
use Mustache_LambdaHelper;

/**
 * Renders historical Mustache templates with safe SQL helper callbacks.
 */
class renderer_service {
    /** @var sql_engine SQL execution service. */
    private readonly sql_engine $sqlengine;

    /**
     * Creates a new renderer service instance.
     *
     * @param sql_engine $sqlengine SQL engine.
     */
    public function __construct(sql_engine $sqlengine = new sql_engine()) {
        $this->sqlengine = $sqlengine;
    }

    /**
     * Renders the configured historical Mustache snapshot.
     *
     * @param string $template Mustache template source.
     * @param task_context $context Execution context.
     * @param array $mustachecontext Mustache context.
     * @return string Rendered historical HTML.
     */
    public function render(string $template, task_context $context, array $mustachecontext = []): string {
        if (trim($template) === '') {
            return '';
        }

        $params = $context->get_sql_params();

        $mustache = new mustache_engine([
            'escape' => static fn($value) => s((string)$value),
            'helpers' => [
                'sqlecho' => function(string $text, Mustache_LambdaHelper $helper) use ($params): string {
                    $sql = trim($text);
                    return s($this->sqlengine->echo_value($sql, $params));
                },
                'sqltable' => function(string $text, Mustache_LambdaHelper $helper) use ($params): string {
                    $sql = trim($text);
                    return $this->sqlengine->table($sql, $params);
                },
            ],
        ]);

        $base = [
            'userid' => $context->userid,
            'courseid' => $context->courseid,
            'cmid' => $context->cmid,
            'instanceid' => $context->instanceid,
            'contextid' => $context->contextid,
            'cycleid' => $context->cycleid,
            'kopere_recertid' => $context->recertificationid,
        ];

        return $mustache->render($template, array_merge($base, $mustachecontext));
    }
}
