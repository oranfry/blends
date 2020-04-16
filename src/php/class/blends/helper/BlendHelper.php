<?php
namespace blends\helper;

class BlendHelper
{
    public static function add_field($blend, $field, $after_field = null)
    {
        if ($after_field) {
            $pos = array_search($after_field, array_map(function($v){
                return $v->name;
            }, $blend->fields)) + 1;
        } else {
            $pos = count($blend->fields);
        }

        array_splice($blend->fields, $pos, 0, [$field]);
    }

    public static function add_tag_field($blend, $fieldname, $place = null, $after_field = null)
    {
        $tagvalues = [];

        if ($place) {
            foreach (is_array($place) ? $place : [$place] as $pl) {
                list($table, $field) = explode('.', $pl);
                $tagvalues = array_merge($tagvalues, get_values($table, $field));
            }

            $tagvalues = array_values(array_unique($tagvalues));

            sort($tagvalues);
        }

        static::add_field($blend, (object) [
           'name' => $fieldname,
           'type' => 'text',
           'filteroptions' => $tagvalues,
           'groupable' => true,
           'main' => true,
        ], $after_field);
    }
}