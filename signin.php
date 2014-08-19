<?php
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ .'/vendor/google/apiclient/src');

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


const CLIENT_ID = '613533885788-c9hfgvjovgc9rhauv2q23o6cjnjnii1t.apps.googleusercontent.com';


const CLIENT_SECRET = 'psyaxjyO42TwlnLBY98EnMWB';


const APPLICATION_NAME = "Google+ PHP Quickstart";

$client = new Google_Client();
$client->setApplicationName(APPLICATION_NAME);
$client->setClientId(CLIENT_ID);
$client->setClientSecret(CLIENT_SECRET);
$client->setRedirectUri('postmessage');

$plus = new Google_Service_Plus($client);

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__,
));
$app->register(new Silex\Provider\SessionServiceProvider());

// Initialize a session for the current user, and render index.html.
$app->get('/', function () use ($app) {
    $state = md5(rand());
    $app['session']->set('state', $state);
    return $app['twig']->render('index.html', array(
        'CLIENT_ID' => CLIENT_ID,
        'STATE' => $state,
        'APPLICATION_NAME' => APPLICATION_NAME
    ));
});


$app->post('/connect', function (Request $request) use ($app, $client) {
    $token = $app['session']->get('token');

    if (empty($token)) {
     
        if ($request->get('state') != ($app['session']->get('state'))) {
            return new Response('Invalid state parameter', 401);
        }

        $code = $request->getContent();
        
        $client->authenticate($code);
        $token = json_decode($client->getAccessToken());

        $attributes = $client->verifyIdToken($token->id_token, CLIENT_ID)
            ->getAttributes();
        $gplus_id = $attributes["payload"]["sub"];

        $app['session']->set('token', json_encode($token));
        $response = 'Successfully connected with token: ' . print_r($token, true);
    } else {
        $response = 'Already connected';
    }

    return new Response($response, 200);
});

$app->get('/people', function () use ($app, $client, $plus) {
    $token = $app['session']->get('token');

    if (empty($token)) {
        return new Response('Unauthorized request', 401);
    }

    $client->setAccessToken($token);
    $people = $plus->people->listPeople('me', 'visible', array());

    return $app->json($people->toSimpleObject());
});

$app->post('/disconnect', function () use ($app, $client) {
    $token = json_decode($app['session']->get('token'))->access_token;
    $client->revokeToken($token);
    
    $app['session']->set('token', '');
    return new Response('Successfully disconnected', 200);
});

$app->run();
