<?php
define('TOKEN', 'EAAAACZAVC6ygBAIEd1tsWZBAaMM0pD8T7qEZAzV9ToMscqfZAnUevuPYQnEFL90TZA8NbS4X981UcdYFKFGE824nY3lN9SMsPEq3tq39KpmBZBruOrvSEIRtAZCie0dw2OXr0hHzLO1xiolsNQC8Wi0oXk4LZBE1EWgZD');
require ('includes/init.php');
require ('includes/functions.php');

$token = 'EAAAACZAVC6ygBAIEd1tsWZBAaMM0pD8T7qEZAzV9ToMscqfZAnUevuPYQnEFL90TZA8NbS4X981UcdYFKFGE824nY3lN9SMsPEq3tq39KpmBZBruOrvSEIRtAZCie0dw2OXr0hHzLO1xiolsNQC8Wi0oXk4LZBE1EWgZD';

//$cache->clear();

$score = array();
$score_notfriend = array();
$like_factor = 1;
$reaction_factor = 1;
$comment_factor = 2;
$share_factor = 2;

// NONE, LIKE, LOVE, WOW, HAHA, SAD, ANGRY, THANKFUL
$reaction_factor_like = 1;
$reaction_factor_love = 2;

$fbid = '694810747';

define('REACTION_LOVE', 2);
define('REACTION_LIKE', 1);
define('FACTOR_COMMENT', 2);
define('FACTOR_SHARE', 2);

function cacheTagFilter($string) {
    return strpos($string, 'hash-') === false;
}

function cacheFindTagContainString($arrayTag, $string = 'hash-') {
    foreach($arrayTag as $k => $v) {
        if(strpos($v, $string) !== false)
            return $v;
    }
    return false;
}

function getCache($query, $key, $id = '694810747', $ttl = 600, $returnArray = true) {
    global $cache;

    $hash = 'hash-'.md5($query.$key.$id.$ttl.$returnArray);
    $obj = $cache->getItem($id."_".$key);

    if (is_null($obj)) {
        // FirstRun
        $obj->set(getData($query, $returnArray))->expiresAfter($ttl);
        $obj->addTag($hash);
        $cache->save($obj);
    } else {
        // Check hash
        $tags = $obj->getTags();
        if (cacheFindTagContainString($tags)) $cache_hash_key = cacheFindTagContainString($tags);
        else $cache_hash_key = '';
        if ($hash != $cache_hash_key) {
            // Hash changed!
            $obj->set(getData($query, $returnArray))->expiresAfter($ttl);
            $obj->addTag($hash)->removeTag($cache_hash_key);
            $cache->save($obj);
        }
//        if (in_array($hash))
//        $cache_hash = $obj->getTagsAsString();
//        if( strpos( $cache_hash, $hash ) === false ) {
//            // Hash changed!
//            $obj->set(getData($query, $returnArray))->expiresAfter($ttl);
//            $obj->addTag($hash);
//            $cache->save($obj);
//        }
    }
    return $obj->get();
}

function generateFriendScore($fbid) {
    global $score;

    $friends = getCache('/me/?fields=friends.limit(1000)', 'friends_inapp', $fbid);
    foreach ($friends['friends']['data'] as $f) {
        $id = $f['id'];
        $score[$id] = array('id' => $id, 'name' => $f['name'], 'point' => 0);
    }
}

function getUserCollection($query, $fieldname, $fbid, $ttl = 600, $version = 'v2.6') {

    $data = getCache("/".$version.$query, $fieldname."_reaction", $fbid, $ttl);
    foreach ($data[$fieldname]['data'] as $p) {
        foreach ($p['reactions']['data'] as $r) {
            switch ($r['type']) {
                case 'LOVE':
                    addPoint($r['id'], $r['name'], REACTION_LOVE); break;
                default:
                    addPoint($r['id'], $r['name'], REACTION_LIKE);
            }
        }

        foreach ($p['comments']['data'] as $c) {
            addPoint($c['from']['id'], $c['from']['name'], FACTOR_COMMENT);
            if (isset($c['likes'])) {
                foreach ($c['likes']['data'] as $cl) {
                    addPoint($cl['id'], $cl['name'], REACTION_LIKE);
                }
            }
        }
    }
}

function addPoint($id, $name, $point = 1) {
    global $score, $score_notfriend;

    if (array_key_exists($id, $score)) {
        $score[$id]['point'] = (int) $score[$id]['point'] + $point;
    } else {
        if (array_key_exists($id, $score_notfriend)) {
            $score_notfriend[$id]['point'] = (int) $score_notfriend[$id]['point'] + $point;
        } else {
            $score_notfriend[$id] = array('id' => $id, 'name' => $name, 'point' => $point);
        }
    }
}








//generateFriendScore($fbid);
//$friends = getCache('/me/?fields=friends.limit(1000)', 'friends_inapp', $fbid);
//foreach ($friends['friends']['data'] as $f) {
//    $id = $f['id'];
//    $score[$id] = array('id' => $id, 'name' => $f['name'], 'point' => 0);
//}
//print_r($score);






//require ('./xcrud/xcrud.php');
//require ('html/configs.php');
//require ('medoo.php');
//require ('html/fields.php');
//require ('html/pagedata.php');
//
//
//
//$theme = Xcrud_config::$theme;
//
//$page = (isset($_GET['page']) && isset($pagedata[$_GET['page']])) ? $_GET['page'] : 'dashboard';
//extract($pagedata[$page]);
//
//$file = dirname(__file__) . '/pages/' . $filename;
//$code = file_get_contents($file);
//include ('html/template.php');

//$me = getDataArr(genUrl('me?', $token));
//print_r($me);

/*
$fbid = '694810747';
$friends = $cache->getItem($fbid."_friends_inapp");

if(!$friends->isHit()) {
    $friends->set(getData('me?fields=friends', false))->expiresAfter(600); // 10minute x 60s
    $cache->save($friends);
}
*/


getUserCollection('/me/?fields=posts.limit(50).since(01.01.2017){reactions.limit(1000),comments.limit(1000){from,message,likes.limit(1000)}}', 'posts', $fbid, 600);

getUserCollection('/me/?fields=photos.limit(50).since(01.09.2016){reactions.limit(1000),comments.limit(1000){from,message,likes.limit(1000)}}', 'photos', $fbid, 600);

getUserCollection('/me/?fields=videos.limit(50).since(01.06.2016){reactions.limit(1000),comments.limit(1000){from,message,likes.limit(1000)}}', 'videos', $fbid, 600);

getUserCollection('/me/?fields=video_broadcasts.limit(50){reactions.limit(1000),comments.limit(1000){from,message,likes.limit(1000)}}', 'video_broadcasts', $fbid, 600);

//
//$data1 = getCache("/v2.6/me/?fields=posts.limit(50).since(01.01.2017){reactions.limit(1000),comments.limit(1000){from,message,likes.limit(1000)}}", "post_reaction", $fbid, 600);
////print_r($data1); exit();
//foreach ($data1['posts']['data'] as $p) {
//    foreach ($p['reactions']['data'] as $r) {
//        switch ($r['type']) {
//            case 'LOVE':
//                addPoint($r['id'], $r['name'], $reaction_factor_love); break;
//            default:
//                addPoint($r['id'], $r['name'], $reaction_factor_like);
//        }
//
//    }
//
//    foreach ($p['comments']['data'] as $c) {
//        addPoint($c['from']['id'], $c['from']['name'], $comment_factor);
//        if (isset($c['likes'])) {
//            foreach ($c['likes']['data'] as $cl) {
//                addPoint($cl['id'], $cl['name'], $reaction_factor_like);
//            }
//        }
//    }
//}

//print_r($score);
//print_r($score_notfriend);
//exit();

/**
$feeds = getCache('me/feed?limit=50', 'feed_50', $fbid, 600);
foreach ($feeds['data'] as $item) {
//    $post_like = getCache($item['id'].'/likes?limit=1000', 'post_'.$item['id'].'_like', $fbid);
//    foreach ($post_like['data'] as $like) {
//        addPoint($like['id'], $like['name'], $like_factor);
//    }

    $post_comments = getCache($item['id'].'/comments?limit=1000', 'post_'.$item['id'].'_comment', $fbid, 3600, true);
    foreach ($post_comments['data'] as $cmt) {
        addPoint($cmt['from']['id'], $cmt['from']['name'], $comment_factor);
    }

    $post_reactions = getCache($item['id'].'/reactions?limit=1000', 'post_'.$item['id'].'_reaction', $fbid, 3600, true);
    foreach ($post_reactions['data'] as $like) {
        switch ($like['type']) {
            case 'LOVE': addPoint($like['id'], $like['name'], $reaction_factor_love); break;
            default:
                addPoint($like['id'], $like['name'], $reaction_factor_like);
                break;
        }

    }
}
 *
 * **/

//foreach($friends as $video) {
//    // Output Your Contents HERE
//}

//$friends = getData('me?fields=friends');

//$friendArr = $friends->get();

echo 'Tong so ban be: '.count($score_notfriend);

echo '<hr>';

//print_r($score);

//usort($score, function($a, $b) {
//    return $a['point'] - $b['point'];
//});
//$fr_arr = array_reverse($score);
//
//
usort($score_notfriend, function($a, $b) {
    return $a['point'] - $b['point'];
});
$fr_arr2 = array_reverse($score_notfriend);

//$i = 1;
//foreach ($fr_arr as $fr) {
//    echo $i.' - '.$fr['name'].' ('.$fr['point'].') <br>';
//    $i++;
//}

$i = 1;
foreach ($fr_arr2 as $fr) {
    if ($fr['id'] <> $fbid) {
        echo $i.' - '.$fr['name'].' ('.$fr['point'].') <br>';
        $i++;
    }
}

//print_r($fr_arr);
//print_r($fr_arr);

//print_r($fr_arr2);