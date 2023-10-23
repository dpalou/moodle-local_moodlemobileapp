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
 * Helper functions for converting a Moodle WS structure to a TS type.
 * If 4.2 or greater use ws_functions.php
 */


require_once("$CFG->libdir/externallib.php");

/**
 * Return all WS structures.
 */
function get_all_ws_structures() {
    global $DB;

    // get all the function descriptions
    $functions = $DB->get_records('external_functions', array('services' => 'moodle_mobile_app'), 'name');
    $functiondescs = array();
    foreach ($functions as $function) {
        $functiondescs[$function->name] = external_api::external_function_info($function);
    }

    return $functiondescs;
}

/**
 * Convert a certain element into a TS structure.
 */
function convert_to_ts($key, $value, $boolisnumber = false, $indentation = '', $arraydesc = '') {
    if ($value instanceof external_value || $value instanceof external_warnings || $value instanceof external_files) {
        // It's a basic field or a pre-defined type like warnings.
        $type = 'string';

        if ($value instanceof external_warnings) {
            $type = 'CoreWSExternalWarning[]';
        } else if ($value instanceof external_files) {
            $type = 'CoreWSExternalFile[]';
        } else if ($value->type == PARAM_BOOL && !$boolisnumber) {
            $type = 'boolean';
        } else if (($value->type == PARAM_BOOL && $boolisnumber) || $value->type == PARAM_INT || $value->type == PARAM_FLOAT ||
                $value->type == PARAM_LOCALISEDFLOAT || $value->type == PARAM_PERMISSION || $value->type == PARAM_INTEGER ||
                $value->type == PARAM_NUMBER) {
            $type = 'number';
        }

        return convert_key_type($key, $type, $value->required, $indentation);

    } else if ($value instanceof external_single_structure) {
        // It's an object.
        $result = convert_key_type($key, '{', $value->required, $indentation);

        if ($arraydesc) {
            // It's an array of objects. Print the array description now.
            $result .= get_inline_comment($arraydesc);
        }

        $result .= "\n";

        foreach ($value->keys as $key => $value) {
            $result .= convert_to_ts($key, $value, $boolisnumber, $indentation . '    ') . ';';

            if (!$value instanceof external_multiple_structure || !$value->content instanceof external_single_structure) {
                // Add inline comments after the field, except for arrays of objects where it's added at the start.
                $result .= get_inline_comment($value->desc);
            }

            $result .= "\n";
        }

        $result .= "$indentation}";

        return $result;

    } else if ($value instanceof external_multiple_structure) {
        // It's an array.
        $result = convert_key_type($key, '', $value->required, $indentation);

        $result .= convert_to_ts(null, $value->content, $boolisnumber, $indentation, $value->desc);

        $result .= "[]";

        return $result;
    } else if ($value == null) {
        return "{}; // WARNING: Null structure found";
    } else {
        return "{}; // WARNING: Unknown structure: $key " . get_class($value);
    }
}

/**
 * Remove all closures (anonymous functions) in the default values so the object can be serialized.
 */
function remove_default_closures($value) {
    if ($value instanceof external_warnings || $value instanceof external_files) {
        // Ignore these types.

    } else if ($value instanceof external_value) {
        if ($value->default instanceof Closure) {
            $value->default = null;
        }

    } else if ($value instanceof external_single_structure) {

        foreach ($value->keys as $subvalue) {
            remove_default_closures($subvalue);
        }

    } else if ($value instanceof external_multiple_structure) {
        remove_default_closures($value->content);
    }
}
