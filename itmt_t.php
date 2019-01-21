<?php
// вытаскиваем данные по сделке
$deal_data = executeREST('crm.deal.get', array('ID' => intval($_REQUEST['data']['FIELDS']['ID'])));
$targetDeal = "D_".$deal_data['result']['ID'];
$dealResp =  $deal_data['result']['ASSIGNED_BY_ID'];
// собираем порцию задач, отфильтровывая закрытые задачи
$queryUrl = 'https://b24-pjnymy.bitrix24.ru/rest/1/xoy9j2g8qzogcw9f/task.item.list.json';
$queryData = http_build_query(array('ORDER' => array("ID" => desc), 'FILTER' => array("CLOSED_DATE" => ""), 'PARAMS' => array(), 'SELECT' => array()));
$curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_POST => 1,
    CURLOPT_HEADER => 0,
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => $queryUrl,
    CURLOPT_POSTFIELDS => $queryData,
    ));
$result1 = curl_exec($curl);
curl_close($curl);
$result2 = json_decode($result1, true);
$count = $result2['total'];
$lastid = $result2['result'][49]['ID'];
// если общее число незакрытых задач на портале превышает 50, то собираем оставшиеся задачи
if ($count>50) {   
   $result6 = array_merge_recursive($result2, generaterestofTasks($count, $queryUrl, $lastid));    
}
// обновляем задачи из массива
updateTasks($result6['result'], $targetDeal, $dealResp);

// вытаскиваем данные по задаче
function executeREST($method, $params) {

    $queryUrl = 'https://b24-pjnymy.bitrix24.ru/rest/1/xoy9j2g8qzogcw9f/'.$method.'.json';
    $queryData = http_build_query($params);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));
    $result = curl_exec($curl);
    curl_close($curl);
    return json_decode($result, true);
}
// вызов чата для отладки кода
function postToChat($message, $attach = array()) {
    $queryUrl = 'https://b24-pjnymy.bitrix24.ru/rest/1/xoy9j2g8qzogcw9f/im.message.add.json';
    $queryData = http_build_query(
        array(
            "USER_ID" => 1,
            "MESSAGE" => $message,
            "ATTACH" => $attach
        )
    );
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
    ));

    $result = curl_exec($curl);
    curl_close($curl);

    return json_decode($result, true);

}
// вызов оставшейся массы задач
function generaterestofTasks($count, $queryUrl, $lastid) {    
    $result5 = array();
    for ($i = 1; $i <= ceil($count / 50); $i++) {
        $queryData = http_build_query(array('ORDER' => array("ID" => desc), 'FILTER' => array("<ID" => $lastid, "CLOSED_DATE" => ""), 'PARAMS' => array(), 'SELECT' => array()));
        $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
            ));
        $result3 = curl_exec($curl);
        curl_close($curl);              
        $result4 = json_decode($result3, true);
        $lastid = $result4['result'][49]['ID']; 
        $count2 = $result4['total'];
        $result5 = array_merge($result5, $result4);
        if (($i*50)+$count2 == $count) break;              
    }      
    return $result5;
} 
// обновление задач
function updateTasks($arParse, $targetDeal, $dealResp) { 
    foreach ($arParse as $i) {   
       $tId = $i['ID'];
		// собираем детальные данные по задачам: метод приходится использовать и утяжелать запрос, 
		// так как task.item.list не возвращает значения поля UF_CRM_TASK
       $queryUrl = 'https://b24-pjnymy.bitrix24.ru/rest/1/xoy9j2g8qzogcw9f/task.item.getdata.json';
       $queryData = http_build_query(array($tId));
       $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $queryUrl,
            CURLOPT_POSTFIELDS => $queryData,
            ));
        $result7 = curl_exec($curl);
        curl_close($curl);
        $result8 = json_decode($result7, true);        
        if (is_array($result8['result']['UF_CRM_TASK'])) {
            foreach ($result8['result']['UF_CRM_TASK'] as $c) {
				// выбираем задачи относящиеся к указанной сделке, где поменялся ответственный для обновления
                if ($c == $targetDeal && $result8['result']['RESPONSIBLE_ID'] != $dealResp) { 
                    $queryUrl2 = 'https://b24-pjnymy.bitrix24.ru/rest/1/xoy9j2g8qzogcw9f/task.item.update.json';
                    $queryData2 = http_build_query(array($tId, 'TASKDATA' => array("RESPONSIBLE_ID" => $dealResp)));
                    $curl = curl_init();
                        curl_setopt_array($curl, array(
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_POST => 1,
                        CURLOPT_HEADER => 0,
                        CURLOPT_RETURNTRANSFER => 1,
                        CURLOPT_URL => $queryUrl2,
                        CURLOPT_POSTFIELDS => $queryData2,
                        ));
                    $result9 = curl_exec($curl);
                    curl_close($curl);                     
                } 
            }
        }    
    } 
    return true;
} 