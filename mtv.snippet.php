<?php
/*
|--------------------------------------------------------------------------
| SHOWMULTITV
|--------------------------------------------------------------------------
|
| @author:  ShCoder sitemart@gmail.com
| 
| @version: 0.2.1
|
| usage: 
        placeholders
        [[mtv?
            &tvId=`20` 
            &docId=`9` 
            &toPlaceholders=`1`
            &suf=`cnt`
            &display=`1`
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

/*
|
| SETTINGS
|
*/

$tvId = isset($tvId) ? intval($tvId) : '';
$docId = isset($docId) ? intval($docId) : $modx->documentIdentifier;

$tpl = isset($tpl) ? $tpl : ''; // шаблон
$wrapTpl = isset($wrapTpl) ? $wrapTpl : '@CODE: [+wrap+]'; // внешний шаблон  
$firstTpl = isset($firstTpl) ? $firstTpl : '';
$lastTpl = isset($lastTpl) ? $lastTpl : '';
$emptyTpl = isset($emptyTpl) ? $emptyTpl : $wrapTpl;

$display = isset($display) ? intval($display) : 'all'; // сколько уровней парсим в массиве значений мульти тв, 1 или все

$thumbField = isset($thumbField) ? $thumbField : '';
$thumbOptions = isset($thumbOptions) ? $thumbOptions : 'w_100,h_200,far_C,bg_FFFFFF';

// PLACEGOLDERS
$suf = isset($suf) ? $suf : 'cnt';
$toPlaceholders = isset($toPlaceholders) ? $toPlaceholders : '0'; // 1 выводим в виде плейсхолдеров, 0 - парсим чанк
$phFieldName = isset($phFieldName) ? $phFieldName : ''; // имя поля по которому будет формироваться плейсхолдер
$phFieldValue = isset($phFieldValue) ? $phFieldValue : ''; // поле-значение для плейхсолдера
$outPlaceholder = isset($outPlaceholder) ? $outPlaceholder : 0; // 1 out to placeholder, 0 - return out

// PREPARE
$prepare = isset($prepare) ? $prepare : '';
$beforePrepare = isset($beforePrepare) ? $beforePrepare : '';
$afterPrepare = isset($afterPrepare) ? $afterPrepare : '';

// сортировка
$sortDir = isset($sortDir) ? $sortDir : '';

// CONFIG 
$p = &$modx->event->params; // all params
$docData = $modx->getDocument($docId);
$confArr = array_merge($p, $docData); // params and doc data


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


/*
| 
| GET MTV DATA
|
*/

$out = '';

$data = $modx->getTemplateVarOutput(array($tvId), $docId); // пулучаем первое значение массива

if (empty($data)) {
    return \DLTemplate::getInstance($modx)->parseChunk($emptyTpl, array()); 
}

$data = current($modx->getTemplateVarOutput(array($tvId), $docId)); // пулучаем первое значение массива

$arr = json_decode($data, true);

if (!isset($arr['fieldValue']) || count($arr['fieldValue']) < 1) {
    return \DLTemplate::getInstance($modx)->parseChunk($emptyTpl, array());
}
    
$mtvArr = $arr['fieldValue'];

// СОРТИРОВКА
// в обратную сторону
if ($sortDir == 'DESC') {
    $mtvArr = array_reverse($mtvArr);        
}


/*
|
| CONFIG PREPARE
| обрабатывается документ и общий данные mtv
|
*/

if (!empty($beforePrepare)) {
    $dataPrepare = $modx->runSnippet($beforePrepare, array('data' => $confArr));

    if (is_array($dataPrepare) && count($dataPrepare) > 0) {
        $confArr = array_merge($confArr, $dataPrepare);
    } 
}


/*
|
| PARSE ROW MTV DATA
|
*/

// Сколько значений выводим
$totalCnt = count($mtvArr);
$cnt =  $display == 'all' ? $totalCnt : $display;
$confArr['cnt'] = $cnt;
$countArr['totalCnt'] = $totalCnt;

$i = 0;
for ($i=0; $i < $cnt; $i++) { 
    
    $dataArr = $mtvArr[$i];

    $dataArr['id'] = $docId;
    $dataArr['num'] = $i + 1;

    $confArr['parsedItems'] = $i + 1;

    /*
    * PREPARE 
    */
    if (!empty($prepare)) {
        $dataPrepare = $modx->runSnippet($prepare, array('data' => $dataArr, 'config' => $confArr));

        if (is_array($dataPrepare) && count($dataPrepare) > 0) {
            $dataArr = array_merge($dataArr, $dataPrepare);
        } else {
            continue;
        }
    }
    
    /*
    * TO PLACEHOLDERS
    */
    if ($toPlaceholders == 1) {

        if (!empty($phFieldName) && isset($dataArr[$phFieldName]) && isset($dataArr[$phFieldValue])) {
            $modx->setPlaceholder($suf.'.'.$dataArr[$phFieldName], $dataArr[$phFieldValue]);
            continue;
        }

        // все значения переводим в плейсхоледры
        foreach ($dataArr as $name => $value) {
            $num = '-'.($i+1); // [+phone-1+], [+phone-2+]
            $modx->setPlaceholder($suf.'.'.$name.$num, $value);
        }

        continue;
    } 

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

    // парсим дополнительные шаблоны row_address, row_desc etc
    foreach ($rowArr as $key => $rowTpl) {
        if (empty($dataArr[$key])) { continue; } // если поле пусто, шаблон не выводим
        $dataArr['row_'.$key] =  \DLTemplate::getInstance($modx)->parseChunk($rowTpl, $dataArr);
    }

    // определяем шаблон
    $parseTpl = $tpl;
    if ($dataArr['num'] === 1 && !empty($firstTpl)) {
        $parseTpl = $firstTpl;
    }
    if ($dataArr['num'] === $cnt && !empty($lastTpl)) {
        $parseTpl = $lastTpl;
    }

    $out .= \DLTemplate::getInstance($modx)->parseChunk($parseTpl, $dataArr);    

}

if ($toPlaceholders == 1) {
    return;
}

/*
|
| AFTER PREPARE
|
*/

if (!empty($afterPrepare)) {
    $dataPrepare = $modx->runSnippet($afterPrepare, array('data' => $confArr));

    if (is_array($dataPrepare) && count($dataPrepare) > 0) {
        $confArr = array_merge($confArr, $dataPrepare);
    } 
}


/*
|
| OUT MTV
|
*/
  
$confArr['wrap'] = $out;

$out = $out != '' 
    ? \DLTemplate::getInstance($modx)->parseChunk($wrapTpl, $confArr) 
    : \DLTemplate::getInstance($modx)->parseChunk($emptyTpl, $confArr);

if ($outPlaceholder == 1) {
    $modx->setPlaceholder($suf.'.mtvout', $out);
} else {
    return $out; 
}
