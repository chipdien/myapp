<?php

function getData($query, $returnArr = true) {
    if ($returnArr) {
        return getDataArr(genUrl($query, TOKEN));
    } else {
        return getDataJson(genUrl($query, TOKEN)); //serialize(getDataArr(genUrl($query, TOKEN)));// getDataJson(genUrl($query, TOKEN));
    }

}

function genUrl($query, $token = TOKEN) {
    $url = 'https://graph.facebook.com/'.$query.'&access_token='.$token;
    return $url;
}

function getDataJson($url) {
    $ch = curl_init();
    curl_setopt_array($ch, array(
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL            => $url,
        )
    );
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function getDataArr($url) {
    return json_decode(getDataJson($url), true);
}