<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Configuration for error reporting, useful to show every little problem during development
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();
date_default_timezone_set("Asia/Ho_Chi_Minh");


define('AUTHOR', 'chipdien');
define('COPYRIGHT', '2014 © FacebookHelper.');

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';
require 'includes/functions.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim();

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
//$app->view->set('parserOptions', array('debug' => true));
$app->view->setTemplatesDirectory("../Mini/view");

$view = $app->view->getEnvironment();
$view->addGlobal('session', $_SESSION);

/******************************************* THE CONFIGS *******************************************************/

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {

    // pre-application hook, performs stuff before real action happens @see http://docs.slimframework.com/#Hooks
    $app->hook('slim.before', function () use ($app) {

        // SASS-to-CSS compiler @see https://github.com/panique/php-sass
        SassCompiler::run("scss/", "css/");

        // CSS minifier @see https://github.com/matthiasmullie/minify
        $minifier = new MatthiasMullie\Minify\CSS('css/style.css');
        $minifier->minify('css/style.css');

        // JS minifier @see https://github.com/matthiasmullie/minify
        // DON'T overwrite your real .js files, always save into a different file
        //$minifier = new MatthiasMullie\Minify\JS('js/application.js');
        //$minifier->minify('js/application.minified.js');

    });

    // Set the configs for development environment
    $app->config(array(
        'debug' => true,
        'database' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => 'dev_miniastar',
            'db_user' => 'root',
            'db_pass' => 'root'
        ),
        'db' => array(
            'database_type' => 'mysql',
            'database_name' => 'dev_miniastar',
            'server' => 'localhost',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8'
        )

    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    $app->config(array(
        'debug' => false,
        'database' => array(
            'db_host' => '',
            'db_port' => '',
            'db_name' => '',
            'db_user' => '',
            'db_pass' => ''
        ),
        'db' => array(
            'database_type' => 'mysql',
            'database_name' => 'dev_miniastar',
            'server' => 'localhost',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8'
        )
    ));
});


use phpFastCache\CacheManager;
use phpFastCache\Core\phpFastCache;

CacheManager::setDefaultConfig([
    "path" => sys_get_temp_dir(),
]);
$cache = CacheManager::getInstance('files');

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
use Medoo\Medoo;
$db = new Medoo($app->config('db'));

$model = new \Mini\Model\Model($app->config('database'));


$authenticate = function ($app) {
    return function () use ($app) {
        if (!isset($_SESSION['fbuser'])) {
            $_SESSION['urlRedirect'] = $app->request()->getPathInfo();
            $app->flash('errors', ['token' => 'Cần phải đăng nhập để sử dụng chức năng này']);
            $app->redirect('/login');
        }
    };
};




/************************************ THE ROUTES / CONTROLLERS *************************************************/

// GET request on homepage, simply show the view template index.twig
$app->get('/', function () use ($app, $db) {
    $data = [
        'page' => [
            'title' => 'FacebookHelper',
            'description' => '',
            'author' => AUTHOR,
            'bodyclass' => 'page-container-bg-solid',
            'copyright' => COPYRIGHT
        ]
    ];

    $app->render('index.twig', $data);
});

$app->get('/login', function () use ($app) {
    if (isset($_SESSION['fbuser'])) {
        $app->redirect('/');
    }
    $data = [
        'page' => [
            'title' => 'Đăng nhập',
            'description' => '',
            'author' => AUTHOR,
            'bodyclass' => 'login',
            'copyright' => COPYRIGHT
        ]
    ];
    $app->render('login.twig', $data);
});

$app->post('/login', function () use ($app, $db) {
    $errors = array();
    $token = $app->request()->post('token_htc');

    if (!$token) {
        $errors['token'] = "Vui lòng nhập Token để đăng nhập";
    } else {
        $fbuser = getData('me?fields=id,name,picture,gender,locale,languages,link,third_party_id,installed,timezone,updated_time,verified,birthday,cover,currency,education,email,hometown,interested_in,location,political,religion,website,work,relationship_status,about,age_range,mobile_phone,favorite_athletes,favorite_teams,inspirational_people,sports,quotes,significant_other,suggested_groups', $token);
        if (isset($fbuser['error'])) {
            $errors['token'] = "Token vô hiệu, vui lòng thử Token khác";
        } else {
            $db_array = array(
                'fib' => $fbuser['id'],
                'token' => $token,
                'name' => $fbuser['name'],
                'email' => $fbuser['email'],
                'data' => $fbuser,
                'status' => 1,
                'created_at'=> date('Y-m-d H:i:s')
            );
            $check = $db->get('users', 'id', ['fid' => $fbuser['id']]);
            if ($check) {
                $db->update('users', ['status' => 1, 'data' => $fbuser, 'modified_at' => date('Y-m-d H:i:s')], ['id' => $check]);
            } else {
                $db->insert('users', $db_array);
            }


            $_SESSION['name'] = $fbuser['name'];
            $_SESSION['email'] = $fbuser['email'];
            $_SESSION['uid'] = $fbuser['id'];
            $_SESSION['token'] = $token;
            $_SESSION['fbuser'] = $fbuser;

            if (isset($_SESSION['urlRedirect'])) {
                $tmp = $_SESSION['urlRedirect'];
                unset($_SESSION['urlRedirect']);
                $app->redirect($tmp);
            }
            $app->redirect('/');
        }
    }

    if (count($errors) > 0) {
        $app->flash('errors', $errors);
        $app->redirect('/login');
    }

//    if (isset($_POST['token_htc'])) {
////        $token = $_POST['token_htc'];
//        $fbuser = getData('me?fields=id,name,picture,gender,locale,languages,link,third_party_id,installed,timezone,updated_time,verified,birthday,cover,currency,education,email,hometown,interested_in,location,political,religion,website,work,relationship_status,about,age_range,mobile_phone,favorite_athletes,favorite_teams,inspirational_people,sports,quotes,significant_other,suggested_groups', $token);
//        if (isset($fbuser['error'])) {
//            $data['error_msg'] = 'TOKEN vô hiệu, vui lòng thử token khác để đăng nhập!';
//        } else {
//            $db_array = array(
//                'token' => $token,
//                'name' => $fbuser['name'],
//                'email' => $fbuser['email'],
//                'data' => $fbuser,
//                'status' => 1,
//                'created_at'=> date('Y-m-d H:i:s')
//            );
//            $db->insert('users', $db_array);
//
//            $_SESSION['name'] = $fbuser['name'];
//            $_SESSION['email'] = $fbuser['email'];
//            $_SESSION['uid'] = $fbuser['id'];
//            $_SESSION['token'] = $token;
//            $_SESSION['fbuser'] = $fbuser;
//
//            if (isset($_SESSION['urlRedirect'])) {
//                $tmp = $_SESSION['urlRedirect'];
//                unset($_SESSION['urlRedirect']);
//                $app->redirect($tmp);
//            }
//            $app->redirect('/');
//
//        }
//
//    } else {
//        $data['error_msg'] = 'Phải nhập Token để đăng nhập hệ thống!';
//    }

//    $app->render('login.twig', $data);
});

$app->get('/logout', function () use ($app, $db) {
    if (isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $db->update('users', ['status' => 0], ['token' => $token]);
    }

    session_destroy();
    $app->redirect('/');
});



// GET request on /subpage, simply show the view template subpage.twig
$app->get('/subpage', function () use ($app) {
    $app->render('subpage.twig');
});

// GET request on /subpage/deeper (to demonstrate nested levels), simply show the view template subpage.deeper.twig
$app->get('/subpage/deeper', function () use ($app) {
    $app->render('subpage.deeper.twig');
});

// All requests on /songs and behind (/songs/search etc) are grouped here. Note that $model is passed (as some routes
// in /songs... use the model)
$app->group('/songs', function () use ($app, $model) {

    // GET request on /songs. Perform actions getAmountOfSongs() and getAllSongs() and pass the result to the view.
    // Note that $model is passed to the route via "use ($app, $model)". I've written it like that to prevent creating
    // the model / database connection in routes that does not need the model / db connection.
    $app->get('/', function () use ($app, $model) {

        $amount_of_songs = $model->getAmountOfSongs();
        $songs = $model->getAllSongs();

        $app->render('songs.twig', array(
            'amount_of_songs' => $amount_of_songs,
            'songs' => $songs
        ));
    });

    // POST request on /songs/addsong (after a form submission from /songs). Asks for POST data, performs
    // model-action and passes POST data to it. Redirects the user afterwards to /songs.
    $app->post('/addsong', function () use ($app, $model) {

        // in a real-world app it would be useful to validate the values (inside the model)
        $model->addSong(
            $_POST["artist"], $_POST["track"], $_POST["link"], $_POST["year"], $_POST["country"], $_POST["genre"]);
        $app->redirect('/songs');
    });

    // GET request on /songs/deletesong/:song_id, where :song_id is a mandatory song id.
    // Performs an action on the model and redirects the user to /songs.
    $app->get('/deletesong/:song_id', function ($song_id) use ($app, $model) {

        $model->deleteSong($song_id);
        $app->redirect('/songs');
    });

    // GET request on /songs/editsong/:song_id. Should be self-explaining. If song id exists show the editing page,
    // if not redirect the user. Note the short syntax: 'song' => $model->getSong($song_id)
    $app->get('/editsong/:song_id', function ($song_id) use ($app, $model) {

        $song = $model->getSong($song_id);

        if (!$song) {
            $app->redirect('/songs');
        }

        $app->render('songs.edit.twig', array('song' => $song));
    });

    // POST request on /songs/updatesong. Self-explaining.
    $app->post('/updatesong', function () use ($app, $model) {

        // passing an array would be better here, but for simplicity this way it okay
        $model->updateSong($_POST['song_id'], $_POST["artist"], $_POST["track"], $_POST["link"], $_POST["year"],
            $_POST["country"], $_POST["genre"]);

        $app->redirect('/songs');
    });

    // GET request on /songs/ajaxGetStats. In this demo application this route is used to request data via
    // JavaScript (AJAX). Note that this does not render a view, it simply echoes out JSON.
    $app->get('/ajaxGetStats', function () use ($app, $model) {

        $amount_of_songs = $model->getAmountOfSongs();
        $app->contentType('application/json;charset=utf-8');
        echo json_encode($amount_of_songs);
    });

    // POST request on /search. Self-explaining.
    $app->post('/search', function () use ($app, $model) {

        $result_songs = $model->searchSong($_POST['search_term']);

        $app->render('songs.search.twig', array(
            'amount_of_results' => count($result_songs),
            'songs' => $result_songs
        ));
    });

    // GET request on /search. Simply redirects the user to /songs
    $app->get('/search', function () use ($app) {
        $app->redirect('/songs');
    });

});


$app->get('/getposts', $authenticate($app), function () use ($app, $db) {
    $data = array();


    $app->render('get_posts.twig', $data);
});









/******************************************* RUN THE APP *******************************************************/

$app->run();

print_r($_SESSION);

