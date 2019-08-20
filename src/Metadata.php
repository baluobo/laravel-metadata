<?php

namespace Daqimei;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Metadata
{
    /**
     * @param $meta_type
     * @param $object_id
     * @param $meta_key
     * @param $meta_value
     * @param bool $unique
     * @return bool
     */
    public function add($meta_type, $object_id, $meta_key, $meta_value, $unique = false)
    {
        if (!$meta_type || !$meta_key || !is_numeric($object_id)) {
            return false;
        }

        $object_id = abs(intval($object_id));
        if (!$object_id) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        if ($unique && DB::table($table)->where(['meta_key' => $meta_key, $column => $object_id])->count()) {
            return false;
        }

        $mid = DB::table($table)->insertGetId([
            $column => $object_id,
            'meta_key' => $meta_key,
            'meta_value' => $meta_value
        ]);

        if (!$mid)
            return false;

        Cache::forget($meta_type . '_meta_' . $object_id);

        return $mid;
    }

    /**
     * @param $meta_type
     * @param $object_id
     * @param $meta_key
     * @param $meta_value
     * @param string $prev_value
     * @return bool
     */
    public function update($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '')
    {
        if (!$meta_type || !$meta_key || !is_numeric($object_id)) {
            return false;
        }

        $object_id = abs(intval($object_id));
        if (!$object_id) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        if (empty($prev_value)) {
            $old_value = self::get($meta_type, $object_id, $meta_key);
            if (count($old_value) == 1) {
                if ($old_value[0] === $meta_value)
                    return false;
            }
        }

        $meta_ids = DB::table($table)
            ->where(['meta_key' => $meta_key, $column => $object_id])
            ->pluck('meta_id');
        if (empty($meta_ids)) {
            return $this->add($meta_type, $object_id, $meta_key, $meta_value);
        }

        $data = compact('meta_value');
        $where = [
            $column => $object_id,
            'meta_key' => $meta_key
        ];

        if (!empty($prev_value)) {
            $where['meta_value'] = $prev_value;
        }

        $result = DB::table($table)->where($where)->update($data);
        if (!$result) {
            return false;
        }

        Cache::forget($meta_type . '_meta_' . $object_id);

        return true;
    }

    /**
     * @param $meta_type
     * @param $object_id
     * @param $meta_key
     * @param string $meta_value
     * @param bool $delete_all
     * @return bool
     */
    public function delete($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false)
    {
        if (!$meta_type || !$meta_key || !is_numeric($object_id) && !$delete_all) {
            return false;
        }

        $object_id = abs(intval($object_id));
        if (!$object_id && !$delete_all) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        $where['meta_key'] = $meta_key;

        if (!$delete_all)
            $where[$column] = $object_id;

        if ('' !== $meta_value && null !== $meta_value && false !== $meta_value)
            $where['meta_value'] = $meta_value;

        $meta_ids = DB::table($table)->where($where)->pluck('meta_id');
        if (!count($meta_ids))
            return false;

        if ($delete_all) {
            if ('' !== $meta_value && null !== $meta_value && false !== $meta_value) {
                $object_ids = DB::table($table)
                    ->where(['meta_key' => $meta_key, 'meta_value' => $meta_value])
                    ->pluck($column);
            } else {
                $object_ids = DB::table($table)->where('meta_key', $meta_key)->pluck($column);
            }
        }

        $count = DB::table($table)->whereIn('meta_id', $meta_ids)->delete();

        if (!$count)
            return false;

        if ($delete_all) {
            foreach ($object_ids as $o_id) {
                Cache::forget($meta_type . '_meta_' . $o_id);
            }
        } else {
            Cache::forget($meta_type . '_meta_' . $object_id);
        }

        return true;
    }

    /**
     * @param $meta_type
     * @param $object_id
     * @param string $meta_key
     * @param bool $single
     * @return array|bool|mixed|string
     */
    public function get($meta_type, $object_id, $meta_key = '', $single = false)
    {
        if (!$meta_type || !is_numeric($object_id)) {
            return false;
        }

        $object_id = abs(intval($object_id));
        if (!$object_id) {
            return false;
        }

        $meta_cache = Cache::get($meta_type . '_meta_' . $object_id);

        if (!$meta_cache) {
            $meta_cache = $this->updateCache($meta_type, [$object_id]);
            $meta_cache = $meta_cache[$object_id];
        }

        if (!$meta_key) {
            return $meta_cache;
        }

        if (isset($meta_cache[$meta_key])) {
            if ($single)
                return $meta_cache[$meta_key][0];
            else
                return $meta_cache[$meta_key];
        }

        if ($single)
            return '';
        else
            return [];
    }

    /**
     * @param $meta_type
     * @param $object_id
     * @param $meta_key
     * @return bool
     */
    public function exists($meta_type, $object_id, $meta_key)
    {
        if (!$meta_type || !is_numeric($object_id)) {
            return false;
        }

        $object_id = abs(intval($object_id));
        if (!$object_id) {
            return false;
        }

        $meta_cache = Cache::get($meta_type . '_meta_' . $object_id);

        if (!$meta_cache) {
            $meta_cache = $this->updateCache($meta_type, [$object_id]);
            $meta_cache = $meta_cache[$object_id];
        }

        if (isset($meta_cache[$meta_key]))
            return true;

        return false;
    }

    /**
     * @param $meta_type
     * @param $meta_id
     * @return bool|\Illuminate\Database\Query\Builder|mixed
     */
    public function getByMid($meta_type, $meta_id)
    {
        if (!$meta_type || !is_numeric($meta_id) || floor($meta_id) != $meta_id) {
            return false;
        }

        $meta_id = intval($meta_id);
        if ($meta_id <= 0) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $meta = DB::table($table)->where('meta_id', '=', $meta_id)->first();
        if (empty($meta)) {
            return false;
        }

        return $meta;
    }

    /**
     * @param $meta_type
     * @param $meta_id
     * @param $meta_value
     * @param bool $meta_key
     * @return bool
     */
    public function updateByMid($meta_type, $meta_id, $meta_value, $meta_key = false)
    {
        if (!$meta_type || !is_numeric($meta_id) || floor($meta_id) != $meta_id) {
            return false;
        }

        $meta_id = intval($meta_id);
        if ($meta_id <= 0) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        if ($meta = $this->getByMid($meta_type, $meta_id)) {
            $original_key = $meta['meta_key'];
            $object_id = $meta[$column];

            if (false === $meta_key) {
                $meta_key = $original_key;
            } elseif (!is_string($meta_key)) {
                return false;
            }

            $data = [
                'meta_key' => $meta_key,
                'meta_value' => $meta_value
            ];

            $where['meta_id'] = $meta_id;

            $result = DB::table($table)->where($where)->update($data);
            if (!$result)
                return false;

            Cache::forget($meta_type . '_meta_' . $object_id);

            return true;
        }

        return false;
    }

    /**
     * @param $meta_type
     * @param $meta_id
     * @return bool
     */
    public function deleteByMid($meta_type, $meta_id)
    {
        if (!$meta_type || !is_numeric($meta_id) || floor($meta_id) != $meta_id) {
            return false;
        }

        $meta_id = intval($meta_id);
        if ($meta_id <= 0) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        if ($meta = $this->getByMid($meta_type, $meta_id)) {
            $object_id = $meta[$column];

            $result = (bool)DB::table($table)->where('meta_id', '=',$meta_id)->delete();

            Cache::forget($meta_type . '_meta_' . $object_id);

            return $result;
        }

        return false;
    }

    /**
     * @param $meta_type
     * @param $object_ids
     * @return array|bool
     */
    public function updateCache($meta_type, $object_ids)
    {
        if (!$meta_type || !$object_ids) {
            return false;
        }

        $table = $this->getTable($meta_type);
        if (!$table) {
            return false;
        }

        $column = $meta_type . '_id';

        if (!is_array($object_ids)) {
            $object_ids = preg_replace('|[^0-9,]|', '', $object_ids);
            $object_ids = explode(',', $object_ids);
        }

        $object_ids = array_map('intval', $object_ids);

        $cache_key = $meta_type . '_meta_';
        $ids = [];
        $cache = [];
        foreach ($object_ids as $id) {
            $cached_object = Cache::get($cache_key . $id);
            if (!$cached_object)
                $ids[] = $id;
            else
                $cache[$id] = $cached_object;
        }

        if (empty($ids))
            return $cache;

        $id_list = join(',', $ids);
        $meta_list = DB::table($table)
            ->select($column, 'meta_key', 'meta_value')
            ->whereIn($column, $id_list)
            ->orderBy('meta_id', 'asc')
            ->get();
        if (!empty($meta_list)) {
            foreach ($meta_list as $meta_row) {
                $mpid = intval($meta_row[$column]);
                $mkey = $meta_row['meta_key'];
                $mval = $meta_row['meta_value'];

                if (!isset($cache[$mpid]) || !is_array($cache[$mpid]))
                    $cache[$mpid] = [];
                if (!isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]))
                    $cache[$mpid][$mkey] = [];

                $cache[$mpid][$mkey][] = $mval;
            }
        }

        foreach ($ids as $id) {
            if (!isset($cache[$id]))
                $cache[$id] = [];
            Cache::forever($cache_key . $id, $cache[$id]);
        }

        return $cache;
    }

    /**
     * @param $meta_type
     * @return bool|string
     */
    public function getTable($meta_type)
    {
        $table_name = $meta_type . '_meta';
        $result = DB::statement("SHOW TABLES LIKE '" . $table_name . "'");
        if (!$result) {
            return false;
        }

        return $table_name;
    }
}
