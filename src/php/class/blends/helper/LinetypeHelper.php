<?php
namespace blends\helper;

class LinetypeHelper
{
    public static function remove_field($linetype, $fieldname)
    {
        $pos = array_search($fieldname, array_map(function($f){
            return $f->name;
        }, $linetype->fields));

        if ($pos !== false) {
            array_splice($linetype->fields, $pos, 1);
        }
    }

    public static function remove_child($linetype, $childlabel)
    {
        $pos = array_search($childlabel, array_map(function($c){
            return $c->label;
        }, $linetype->children));

        if ($pos !== false) {
            array_splice($linetype->children, $pos, 1);
        }
    }

    public static function add_field($linetype, $field, $after_field = null)
    {
        if ($after_field) {
            $pos = array_search($after_field, array_map(function($v){
                return $v->name;
            }, $linetype->fields)) + 1;
        } else {
            $pos = count($linetype->fields);
        }

        static::remove_field($linetype, $field->name);

        array_splice($linetype->fields, $pos, 0, [$field]);
    }

    public static function add_child($linetype, $child, $after_childlabel = null)
    {
        $pos = count($linetype->fields);

        if ($after_childlabel) {
            $found = array_search($after_childlabel, array_map(function($c){
                return $c->label;
            }, $linetype->children));

            if ($found !== false) {
                $pos = $found + 1;
            }
        }

        static::remove_child($linetype, $child->label);

        array_splice($linetype->children, $pos, 0, [$child]);
    }

    public static function add_tag_field($linetype, $fieldname, $fuse = null, $unfuses = null, $after_field = null)
    {
        $variable = ":{t}_{$fieldname}";

        $field = (object)[
            'name' => $fieldname,
            'type' => 'text',
        ];

        if ($fuse) {
            $field->fuse = $fuse;
        }

        static::add_field($linetype, $field, $after_field);

        foreach ($unfuses ?: [] as $unfuse) {
            $linetype->unfuse_fields[$unfuse] = $variable;
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
