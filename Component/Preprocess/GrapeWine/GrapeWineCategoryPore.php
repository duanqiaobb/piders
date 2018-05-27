<?php
namespace Preprocess\GrapeWine;
use Pider\Prepost\Data\Pore;
use Pider\Prepost\Data\Rule;
use Pider\Prepost\Data\Throttle;
use Pider\Prepost\Data\Reaction;


class GrapeWineCategoryPore extends Pore {
    private $ikeywords = [
        '葡萄酒种类',
        '葡萄酒类型'
    ];
    
    protected function selfFeatures():array {
        $this->self_datas = [
            '干邑'=>'Cognac',
            '红葡萄酒'=>'Red Wine',
            '白葡萄酒'=>'White Wine',
            '起泡酒'=> 'Sparkling Wine',
            '雅文邑'=> 'Armagnac',
            '桃红葡萄酒'=>'Rose Wine',
            '加强型葡萄酒'=>'Fortified Wine',
            '黄葡萄酒' => 'Vin Jaune',
            '白兰地' => 'Brandy',
            '甜葡萄酒'=>'Sweet Wine',
            '格拉帕酒'=>'Grappa'
        ];
        $WineCategoryPropertyExist = new Throttle(function($data) {
            $subdata = [];
            foreach($data as $p_name => $p_value ) {
                $p_name_regex = '/'.implode($this->ikeywords,'|').'/i';
                $p_value = trim($p_value);
                $p_name = trim($p_name);
                if (preg_match($p_name_regex,$p_name) || array_key_exists($p_value,$this->self_datas) || in_array($p_value,$this->self_datas)) {
                    $subdata[$p_name] = $p_value;
                }
            }
            return $subdata;
        });

        $GragulateReaction = new class($WineCategoryPropertyExist) extends Reaction {
            public function react(array $data,Pore $pore):array {
                $clean_data = [];
                if (count($data) == 0) {
                    $clean_data['type_ch'] = '';
                    $clean_data['type_en'] = '';
                } else if (count($data) >= 1){
                    foreach($data as $key => $value) {
                        if (in_array($key,$pore->self_datas)) {
                            $self_data = array_reverse($pore->self_datas);
                            $clean_data['type_ch']  = $self_data[$value];
                            $clean_data['type_en']  = $value;
                        } else if (array_key_exists($value,$pore->self_datas)) {
                            $clean_data['type_en']  = $value;
                            $clean_data['type_en']  = @$pore->self_datas[$value];
                        }
                    }
                } else {
                    $clean_data['type_ch'] = '';
                    $clean_data['type_en'] = '';
                } 
                return $clean_data;
            }
        };
        return ['reaction'=>[$GragulateReaction],'filter'=>[],'absorber'=>[]];
    }
}

