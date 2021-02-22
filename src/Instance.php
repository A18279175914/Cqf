<?php
namespace Cqf;
class Instance{
    private $redis;
    private $prefix = "cqf_bucket";//初始化桶
    private $hash_val;//初始化值
    private $seed;//种子
    public function __construct($redis)
    {
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
            return $this->redis->getBit($this->prefix,$this->hash_val);
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
            $this->murmurhash32($key);
            return $this->redis->setBit($this->prefix, $this->hash_val, 1);
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
            return $this->redis->setBit($this->prefix, $this->hash_val, 0);
        }catch (\Exception $e){
            echo $e->getMessage();
            return 0;
        }
    }
    private function murmurhash32($tmp_key)
    {
        $key_hash          =  murmurhash3_x64_128($tmp_key,$this->seed);
        $key_hash          =  unpack("C*",$key_hash);
        $this->hash_val="";
        foreach($key_hash as $key=>$val){
            $key_hash_bucket[$key] = decbin($val);
            $len = 8-strlen($key_hash_bucket[$key]);
            for($i = 0;$i<$len;$i++){
                $key_hash_bucket[$key] = '0'.$key_hash_bucket[$key];
            }
        }
        foreach ($key_hash_bucket as $val){
            $this->hash_val .= $val[0];
            $this->hash_val .= $val[5];
        }
        $this->hash_val = bindec($this->hash_val);
    }
}