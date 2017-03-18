<?php

function getCache($query, $key, $id = '694810747', $token, $ttl = 600, $returnArray = true) {
    global $cache;

    $hash = 'hash-'.md5($query.$key.$id.$ttl.$returnArray);
    $obj = $cache->getItem($id."_".$key);

    if (is_null($obj)) {
        // FirstRun
        $obj->set(getData($query, $token, $returnArray))->expiresAfter($ttl);
        $obj->addTag($hash);
        $cache->save($obj);
    } else {
        // Check hash
        $tags = $obj->getTags();
        if (cacheFindTagContainString($tags)) $cache_hash_key = cacheFindTagContainString($tags);
        else $cache_hash_key = '';
        if ($hash != $cache_hash_key) {
            // Hash changed!
            $obj->set(getData($query, $token, $returnArray))->expiresAfter($ttl);
            $obj->addTag($hash)->removeTag($cache_hash_key);
            $cache->save($obj);
        }
    }
    return $obj->get();
}

function cacheFindTagContainString($arrayTag, $string = 'hash-') {
    foreach($arrayTag as $k => $v) {
        if(strpos($v, $string) !== false)
            return $v;
    }
    return false;
}

function getDataWithQuery($query, $token) {

}

function getData($query, $token = TOKEN, $returnArr = true) {
    if ($returnArr) {
        return getDataArr(genUrl($query, $token));
    } else {
        return getDataJson(genUrl($query, $token)); //serialize(getDataArr(genUrl($query, TOKEN)));// getDataJson(genUrl($query, TOKEN));
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