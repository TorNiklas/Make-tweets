<?php

require_once 'config.php';

//Builds and returns the base oAuth string in accordance to the Twitter docs
function buildBaseString($baseURI, $oauthParams){
    $baseStringParts = [];
    ksort($oauthParams);

    foreach($oauthParams as $key => $value){
        $baseStringParts[] = "$key=" . rawurlencode($value);
    }

    return 'POST&' . rawurlencode($baseURI) . '&' . rawurlencode(implode('&', $baseStringParts));
}

//Builds and returns the oAuth header in accordance to the Twitter docs
function buildAuthorizationHeader($oauthParams){
    $authHeader = 'Authorization: OAuth ';
    $values = [];

	foreach($oauthParams as $key => $value) {
        $values[] = "$key=\"" . rawurlencode( $value ) . "\"";
    }

    $authHeader .= implode(', ', $values);
    return $authHeader;
}

//Makes and sends a request to $url using cURL
//Returns response
function curlRequest($oauth, $url, $postfields = null) {
    $header =  array(buildAuthorizationHeader($oauth), 'Expect:');

    $options = array(
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_HEADER => false,
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
    );

    if (!is_null($postfields))
    {
        $options[CURLOPT_POSTFIELDS] = http_build_query($postfields, '', '&');
    }

    $feed = curl_init();
    curl_setopt_array($feed, $options);
    $json = curl_exec($feed);

    $httpStatusCode = curl_getinfo($feed, CURLINFO_HTTP_CODE);
    
    
    curl_close($feed);

    return $json;
}

//Makes and sends a request for the first oauth_token
function sendRequest($host, $path){
    $baseURI = "https://$host$path";

    $oauth = buildOauth($baseURI, null, OAUTH_CALLBACK);
    $response = curlRequest($oauth, $baseURI);

    // echo "Response: ";
    // echo $response;
    // echo "<br><br>";


    $parts = explode('&', $response);


    return [
        'success' => true,
        'message' => false,
        'code' => false,
        'oauth_token' => explode('=', $parts[0])[1],
    ];
}

//Makes, signs, and returns the oAuth to be used in the header
function buildOauth($url, $status = null, $callback = null)
{
    $oauth = array(
        'oauth_consumer_key' => CONSUMER_KEY,
        'oauth_nonce' => md5(uniqid()),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_version' => '1.0',
    );
    
    //Conditional includes
    if(isset($_SESSION["oauth_token"])) {
        $oauth['oauth_token'] = $_SESSION["oauth_token"];
    }
    if(!is_null($status)) {
        $oauth['status'] = $status;
    }
    if(!is_null($callback)) {
        $oauth['oauth_callback'] = $callback;
    }


    $base_info = buildBaseString($url, $oauth);
    if(isset($_SESSION["oauth_secret"])) {
        $composite_key = rawurlencode(CONSUMER_SECRET) . '&' . rawurlencode($_SESSION["oauth_secret"]);
    }
    else {
        $composite_key = rawurlencode(CONSUMER_SECRET) . '&';
    }
    $oauth_signature = base64_encode(hash_hmac('sha1', $base_info, $composite_key, true));
    $oauth['oauth_signature'] = $oauth_signature;
    return $oauth;
}

//Function included to streamline the process of Tweeting
function postTweet($status) {
    $url = 'https://api.twitter.com/1.1/statuses/update.json';
    $oauth = buildOauth($url, $status);
    $postfields = array(
        'status' => $status
    );
    return curlRequest($oauth, $url, $postfields);
}