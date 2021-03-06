<?php
namespace App\Http\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Log;

class Api500EasyPayCacheService
{
    const SURVIVAL_TIME = 1440;
    const SENDLISTLIMIT = 200;
    
    public function __construct()
    {
        Cache::store('redis')->setPrefix('500EasyPay');
    }

    public function setCache($tags, $type, $data)
    {
        $base_id = $tags . '_' . uniqid();
        
        Redis::rpush($tags . '_' . $type, $base_id);
        Cache::store('redis')
            ->tags([$tags])
            ->add($base_id, $data, self::SURVIVAL_TIME);
        
        self::setMerNoOrdeNum($tags, $type, $data, $base_id);
        
        Log::info('# setCache #'  
            . ', ['. $tags . ']' 
            . ', base_id = ' . $base_id 
            . ', data = ' . print_r($data, true) 
            . ', FILE = ' .__FILE__ . 'LINE:' . __LINE__
        );

        return $base_id;
    }

    public function setMerNoOrdeNum($tags, $type, $data, $base_id)
    {
        $order_data = json_decode($data['data']['data']);
        Redis::set(
            $tags . '_' . $type . '_' . $data['config']['merNo'] . '_' .  $order_data->orderNum,
            $base_id
        );
    }

    public function getCache($tags, $type)
    {
        $tasks = Redis::lrange($tags . '_' . $type, 0, -1);
        $task_data = [];
        $return_data = [];

        if (!$tasks) {
            Log::info('# getCache tasks null #'
                . ', [' . $tags . ']'
                . ', type = ' . $type
                . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
            );
            return false;
        }

        Log::info('# get cache tasks #' 
            . ', tasks = ' . print_r($tasks, true)
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );

        foreach ($tasks as $task_base_id) {
            $get_task = Cache::store('redis')
                ->tags([$tags])
                ->get($task_base_id);
            
            if (!$get_task) {
                Log::warning('# get cache warning #' 
                    . ', ['. $tags . ']' 
                    . ', base_id = ' . $task_base_id 
                    . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
                );
                continue;
            }

            $task_data = array_merge($get_task,
                array('base_id' => $task_base_id)
            );
            array_push($return_data, $task_data);
        }

        Log::info('# getCache_data #' 
            . ', return_data = ' . print_r($return_data, true) 
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );

        return $return_data;
    }

    public function setSendCache($tags, $type, $base_id, $data)
    {
        Redis::rpush($tags . '_' . $type, $base_id);
        Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->add($base_id, $data, self::SURVIVAL_TIME);
        
        Log::info('# setSendCache success #'
            . ', ['. $tags . '_' . $type .']'
            . ', base_id = '. $base_id 
            . ', data = ' . print_r($data, true) 
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );
    }

    public function getSendListCache($tags, $type)
    {
        return Redis::lrange($tags . '_' . $type, 0, self::SENDLISTLIMIT);
    }
    
    public function setResponseCache($tags, $type, $base_id, $data)
    {
        Redis::rpush($tags . '_' . $type, $base_id);
        Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->forever($base_id, $data);
        
        Log::info('# setResponseCache #'
            . ', ['. $tags . '_' . $type .']'
            . ', base_id = '. $base_id 
            . ', data = ' . print_r($data, true) 
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );
        // 這做法要再想想(移出去?)
        if ($data['stateCode'] === '00') {
            self::setCallBackWaitCache(
                $tags,
                'call_back_wait',
                $base_id,
                $data
            );
        }

        Log::info('# setCallBackWaitCache #' 
            . ', ['. $tags . '_call_back_wait]' 
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );
    }

    public function saveCallBackCache($tags, $type, $base_id, $data)
    {
        Log::info('# start call_back #'
            . ', [' . $tags . '_' . $type . ']'
            . ', data = ' .$data
            . ', base_id = ' . $base_id
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );
        Redis::rpush($tags . '_' . $type, $base_id);
        
        Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->forever($base_id, $data);
    }

    public function getSaveCallBackList($tags, $type)
    {
        return Redis::lrange($tags . '_' . $type, 0, self::SENDLISTLIMIT);
    }

    /**
     * Wait Call Back function
     *
     * @param [Str] $tags
     * @param [Str] $type
     * @param [Str] $merNo
     * @param [Num] $orderNum
     * @return base_id
     */
    public function getCallBackWaitCache($tags, $type, $merNo, $orderNum)
    {
        return Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->get($merNo . '_' . $orderNum);
    }

    public function checkCallBackCache($tags, $type, $merNo, $orderNum)
    {
        return Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->get($merNo . '_' . $orderNum);
    }

    public function getSendCache($tags, $type, $base_id)
    {
        return Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->get($base_id);
    }

    public function hasQrcode($tags, $type, $merNo, $orderNum)
    {
        return Redis::Get($tags . '_' . $type . '_' . $merNo . '_' . $orderNum);
    }

    public function getResponseQrcodeList($tags, $type)
    {
        return Redis::lrange($tags . '_' . $type, 0, self::SENDLISTLIMIT);
    }

    // TODO exception    
    public function getResponseQrcode($tags, $type, $base_id)
    {
        return Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->get($base_id);
    }
    // TODO exception    
    public function deleteListCache($tags, $type, $base_id)
    {
        $is_delete = Redis::LREM($tags . '_' . $type, 0, $base_id);
        Log::info('# delete list #'
            . ', is_delete = ' . $is_delete
            . ', [' . $tags . '_' . $type .']'
            . ', base_id = ' . $base_id 
            . ', FILE = ' . __FILE__ . 'LINE:' . __LINE__
        );
    }
    // TODO exception
    public function deleteTagsCache($tags, $type, $base_id)
    {
        if (!$type) {
            $is_delete_tags = Cache::store('redis')
                ->tags([$tags])
                ->forget($base_id);
        } else {
            $is_delete_tags = Cache::store('redis')
                ->tags([$tags. '_' . $type])
                ->forget($base_id);
        }
        
        Log::info('# forget tags key#'
            . ', is_delete_tags = ' . $is_delete_tags
            . ', [' . $tags . '_' . $type .']' 
            . ', base_id = ' . $base_id 
            . ', FILE = '. __FILE__ . 'LINE:' . __LINE__
        );
    }

    private function setCallBackWaitCache($tags, $type, $base_id, $data)
    {
        Redis::rpush($tags . '_' . $type, $data['merNo'] . '_' . $data['orderNum']);
        Cache::store('redis')
            ->tags([$tags . '_' . $type])
            ->add($data['merNo'] . '_' . $data['orderNum'], $base_id, self::SURVIVAL_TIME);
    }
}
