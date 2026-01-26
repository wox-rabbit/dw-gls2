<?php
namespace App\Traits;

trait HasEtc
{

    private $etcObjDecoded;

    private function getEtcObj()
    {
        if (empty($etcObjDecoded))
        {
            if (!$this->etc) {
                $etcObj = json_decode("{}");
            }
            elseif (($etcObj = json_decode($this->etc)) === false) {
                return false;
            }
            $etcObjDecoded = $etcObj;
        }
        return $etcObjDecoded;
    }

    /**
     * The `etc` property is a dirty storage.
     *
     * @param array $setters Associative array
     * @return bool
     */
    public function setEtc(Array $setters)
    {
        if (($etcObj = $this->getEtcObj()) === false) return false;

        foreach ($setters as $k => $v)
        {
            if (is_null($v)) {
                unset($etcObj->{$k});
            }
            else {
                $etcObj->{$k} = $v;
            }
        }

        $this->etcObjDecoded = $etcObj;
        $this->etc = json_encode($etcObj);
        $this->save();
        return true;
    }

    public function setEtcSingle(String $key, $value) {
        return $this->setEtc([$key=>$value]);
    }

    public function getEtc(Array $getters)
    {
        if (($etcObj = $this->getEtcObj()) === false) return false;
        $getters = array_flip($getters);
        return array_intersect_key((array)$etcObj, $getters);
    }
    public function getEtcSingle(String $getter)
    {
        $array = $this->getEtc([$getter]);
        return reset($array);
    }
}
