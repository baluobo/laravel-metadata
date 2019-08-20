<?php

namespace Daqimei\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static add($meta_type, $object_id, $meta_key, $meta_value, $unique = false)
 * @method static update($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '')
 * @method static delete($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false)
 * @method static get($meta_type, $object_id, $meta_key = '', $single = false)
 * @method static exists($meta_type, $object_id, $meta_key)
 * @method static getByMid($meta_type, $meta_id)
 * @method static updateByMid($meta_type, $meta_id, $meta_value, $meta_key = false)
 * @method static deleteByMid($meta_type, $meta_id)
 * @method static updateCache($meta_type, $object_ids)
 * @method static getTable($meta_type)
 */
class Metadata extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Daqimei\Metadata::class;
    }
}
