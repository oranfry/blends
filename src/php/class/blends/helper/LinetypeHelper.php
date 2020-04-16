<?php
namespace blends\helper;

class LinetypeHelper
{
    public static function add_tag_field($linetype, $fieldname, $fuse = null, $extra_unfuses = null, $after_field = null)
    {
        $fuse = $fuse ?? "t.{$fieldname}";
        $variable = ":{$fieldname}";

        if ($after_field) {
            $pos = array_search($after_field, array_map(function($v){
                return $v->name;
            }, $linetype->fields)) + 1;
        } else {
            $pos = count($linetype->fields);
        }

        array_splice($linetype->fields, $pos, 0, [(object)[
            'name' => $fieldname,
            'type' => 'text',
            'fuse' => $fuse,
        ]]);

        $linetype->unfuse_fields[$fuse] = $variable;

        if ($extra_unfuses) {
            foreach ($extra_unfuses as $unfuse) {
                $linetype->unfuse_fields[$unfuse] = $variable;
            }
        }
    }

    public static function add_tag_suggestions(&$suggested_values, $fieldname, $place)
    {
        $tagvalues = [];

        foreach (is_array($place) ? $place : [$place] as $pl) {
            list($table, $field) = explode('.', $pl);
            $tagvalues = array_merge($tagvalues, get_values($table, $field));
        }

        $tagvalues = array_values(array_unique($tagvalues));

        sort($tagvalues);

        foreach (is_array($fieldname) ? $fieldname : [$fieldname] as $fn) {
            $suggested_values[$fn] = $tagvalues;
        }
    }
}