<?php
namespace Cqf;
class Instance{
    private $redis;
    private $prefix;//初始化桶
    private $hash_key;//初始化键
    private $hash_val;//初始化值
    private $seed;//种子1hao
    private $bucket_val;//桶里键值对值
    public function __construct($redis)
    {
	    echo 1111;
        try {
            if(!($redis instanceof \Redis))throw new \Exception('该连接不可用');
            if ($redis->ping()) {
                $this->redis = $redis;
            } else {
                throw new \Exception('该连接不可用');
            }
            $this->seed = 0;
        }catch (\Exception $e){
            $this->redis = false;
        }
    }
    private function verifyRedis()
    {
        if($this->redis === false){
            throw new \Exception('redis实列不可用');
        }
    }
    /**
     * @return boolean
     */
    public function get($key)
    {
        try {
            $this->verifyRedis();
            $this->murmurhash32($key);
            return $this->find($this->bucket_val, $this->hash_val);
        }catch (\Exception $e){
            echo $e->getMessage();
            return false;
        }
    }
    /**
     * @return mixed
     */
    public function set($key)
    {
        try {
            $this->verifyRedis();
            return $this->murmurhash32($key);
//            $bucket_val  =  $this->insert($this->bucket_val,$this->hash_val);
//            if($bucket_val === false)return 0;
//            return $this->redis->hSet($this->prefix, $this->hash_key, $bucket_val);
        }catch (\Exception $e){
            echo $e->getMessage();
            return 0;
        }

    }
    /**
     * @return mixed
     */
    public function delete($key)
    {
        try {
            $this->verifyRedis();
            $this->murmurhash32($key);
            $bucket_val  =  $this->deleteKey($this->bucket_val,$this->hash_val);
            if($bucket_val === false)return 0;
            return $this->redis->hSet($this->prefix, $this->hash_key, $bucket_val);
        }catch (\Exception $e){
            echo $e->getMessage();
            return 0;
        }
    }
    private function murmurhash32($tmp_key)
    {
        //这里尝试了在hash散列之前用md5函数先处理下，但冲突率和没用之前一样，只能写一个拓展让php支持64位hash散列函数
//        $key_hash          =   Hash::hash32($key,$this->seed);
        $key_hash          =  murmurhash3_x64_128($tmp_key,$this->seed);
        $key_hash          =  unpack("C*",$key_hash);
        $key_hash_bucket   =  [];
        $this->prefix="";
        $this->hash_val="";
        foreach($key_hash as $key=>$val){
            $key_hash_bucket[$key] = decbin($val);
            $len = 8-strlen($key_hash_bucket[$key]);
            for($i = 0;$i<$len;$i++){
                $key_hash_bucket[$key] = '0'.$key_hash_bucket[$key];
            }
        }
        foreach ($key_hash_bucket as $val){
            $this->prefix .= $val[0];
            $this->hash_val .= $val[5];
        }
//        $key_hash_right    =   $key_hash >> 16;
        //这里是桶的位置
//        $this->prefix      =   ($key_hash_right & 65280) >> 8;
        $prefix            =   $this->prefix;
        $this->prefix      =   "cqf_bucket".$this->prefix;
        //这里是桶里key-value 的key
//        $this->hash_key    =   $key_hash_right  & 255;
        //这里是桶里key-value 的value
//        $this->hash_val    =   $this->getHashVal($key_hash);

        $test_key          =   $prefix.$this->hash_val;
        return   array('key'=>bindec($test_key) ,'val'=>$tmp_key);
        //初始化桶里的key-value
//        $this->bucket_val  =   array_values(unpack('C*', $this->redis->hGet($this->prefix, $this->hash_key)));
    }
    private function getHashVal($key)
    {
        $key = decbin($key);
        $val = $key[31].$key[28].$key[27].$key[25].$key[23].$key[20].$key[19].$key[17];
        return bindec($val);
    }
    private function insert($arr,$key)
    {
        //先找有没有
        if(!$this->find($arr,$key)) {
            //有的话就把对应的位变为1
            $index=floor($key/8);
            $bid=(int)pow(2,(int)($key-8*$index));
            $arr[$index] += $bid;
        }
        array_unshift($arr,"C*");
        return call_user_func_array('pack', $arr);
    }
    private function find($arr,$key)
    {
        //这里是在位运算里找的，我把有关存位运算的位分到了一个byte数组中，一个byte为8位，所以整个byte数组的长度为8192
        //假如数组是空的就代表该key值没有，就直接返回false
        if(!$arr){
            return false;
        }
        //这里是计算在byte数组具体的那个位置
        $index=floor($key/8);
        //这里是在做与运算，假如该byte在那个位置上为1就代表有，否则返回false
        $bid=(int)pow(2,(int)($key-8*$index));
        if($arr[$index] & $bid) return true;
        return false;
    }
    private function findIndex($arr,$key)
    {
        if(!$arr){
            return false;
        }
        $index=floor($key/8);
        $bid=(int)pow(2,(int)($key-8*$index));
        if($arr[$index] & $bid) return $index;
        return false;
    }
    private function deleteKey($arr,$key)
    {
        //也是先找有没有
        $index = $this->findIndex($arr,$key);
        if(!($index === false)){
            //有的话把对应的位变为0
            $bid=(int)pow(2,(int)($key-8*$index));
            $arr[$index] -= $bid;
        }
        array_unshift($arr,"C*");
        return call_user_func_array('pack', $arr);
    }
    public static function testHash(string $key)
    {
        return Hash::hash32($key,0);
    }
}
