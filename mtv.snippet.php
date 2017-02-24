<?php
/*
|--------------------------------------------------------------------------
| SHOWMULTITV
|--------------------------------------------------------------------------
|
| usage: 
        placeholders
        [[mtv?
            &tvId=`20` 
            &docId=`9` 
            &toPlaceholders=`1`
            &suf=`cnt`
            &depth=`1`
        ]]
        TPL
        [[mtv?
            &tvId=`20` 
            &docId=`9` 
            &suf=`cnt`
            &wrapTpl=`@CODE: [+wrap+]`
            &tpl=`@CODE: <div>[+name+] [+email+]</div>`
            &check=`show` //проверяем выводить или нет строку (show: 1 или пусто)
        ]]
*/

include_once(MODX_BASE_PATH . 'assets/snippets/DocLister/lib/DLTemplate.class.php');

// SETTINGS

$tvId = isset($tvId) ? intval($tvId) : '';
$docId = isset($docId) ? intval($docId) : $modx->documentIdentifier;

$tpl = isset($tpl) ? $tpl : ''; // шаблон
$wrapTpl = isset($wrapTpl) ? $wrapTpl : '@CODE: [+wrap+]'; // внешний шаблон  
$firstTpl = isset($firstTpl) ? $firstTpl : '';
$lastTpl = isset($lastTpl) ? $lastTpl : '';

$toPlaceholders = isset($toPlaceholders) ? $toPlaceholders : '0'; // 1 выводим в виде плейсхолдеров, 0 - парсим чанк
$suf = isset($suf) ? $suf : 'cnt';
$depth = isset($depth) ? intval($depth) : 'all'; // сколько уровней парсим в массиве значений мульти тв, 1 или все
$check = isset($check) ? $check : ''; // название параметра (чекбокс) в мульти тв, по которому будет даваться разрешение на вывод
$thumbField = isset($thumbField) ? $thumbField : '';
$thumbOptions = isset($thumbOptions) ? $thumbOptions : 'w_100,h_200,far_C,bg_FFFFFF';
$outPlaceholder = isset($outPlaceholder) ? $outPlaceholder : 0; // 1 out to placeholder, 0 - return out
$prepare = isset($prepare) ? $prepare : '';

$p = &$modx->event->params; // all params

// Формируем кастомный массив шаблонов на оснвое 
// названия поля MTV и параметра сниппета шаблона, в котором содержится шаблон вывода
// address - поле в MTV, row_address - tpl в сниппете
$rowArr = array();
foreach ($p as $key => $value) {
    if (strpos($key, 'row_') !== false) {
        $key = substr($key, 4);
        if (!empty($key)) {
            $rowArr[$key] = !empty($value) ? $value : '@CODE: ';
        }        
    }
}

//$row_address = isset($row_address) ? $row_address : '@CODE ';

// GET TV MULTI TV VALUE

$out = '';

$data = current($modx->getTemplateVarOutput(array($tvId), $docId)); // пулучаем первое значение массива

if (empty($data)) { return; }

$arr = json_decode($data, true);

if (isset($arr['fieldValue']) && count($arr['fieldValue']) > 0) {

    $cnt =  $depth == 'all' ? count($arr['fieldValue']) : $depth;
    
    $i = 0;
    for ($i=0; $i < $cnt; $i++) { 
        
        $dataArr = $arr['fieldValue'][$i];

        $dataArr['id'] = $docId;

        // если нельзя выводить параметр - пропускаем
        if ($check != '' && isset($dataArr[$check]) && $dataArr[$check] != 1) { continue; }

            /*
            * PREPARE 
            */
            if (!empty($prepare)) {
                $dataPrepare = $modx->runSnippet($prepare, array('data' => $dataArr));

                if (is_array($dataPrepare) && count($dataPrepare) > 0) {
                    $dataArr = array_merge($dataArr, $dataPrepare);
                }
            }
            
            /*
            * TO PLACEHOLDERS
            */
            if ($toPlaceholders == 1) {
            
                foreach ($dataArr as $name => $value) {
                    $num = $depth == 'all' ? '-'.($i+1) : ''; // [+phone-1+], [+phone-2+]
                    $modx->setPlaceholder($suf.'.'.$name.$num, $value);
                }
            
            } else {

                /*
                * PARSE TPL
                */

                if(!empty($thumbField) && isset($dataArr[$thumbField])) { 
                    $dataArr['thumb'] = $modx->runSnippet('phpthumb', array(
                        'input' => $dataArr[$thumbField],
                        'options' => $thumbOptions
                        )
                    );
                }

                $dataArr['num'] = $i + 1;

                // парсим дополнительные шаблоны row_address, row_desc etc
                foreach ($rowArr as $key => $rowTpl) {
                    if (empty($dataArr[$key])) { continue; } // если поле пусто, шаблон не выводим
                    $dataArr['row_'.$key] =  \DLTemplate::getInstance($modx)->parseChunk($rowTpl, $dataArr);
                }

                $tpl = $dataArr['num'] === 1 && !empty($firstTpl) ? $firstTpl : $tpl;
                $tpl = $dataArr['num'] === $cnt && !empty($lastTpl) ? $lastTpl : $tpl;

                $out .= \DLTemplate::getInstance($modx)->parseChunk($tpl, $dataArr);
            
            }

    }
  

} 


$out = $out != '' ? \DLTemplate::getInstance($modx)->parseChunk($wrapTpl, array('wrap' => $out)) : '';

if ($outPlaceholder == 1) {
    $modx->setPlaceholder($suf.'.mtvout', $out);
} else {
    return $out; 
}
