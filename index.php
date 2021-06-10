<?php

//Function to log in the browser console
function console_log( $data ){
    echo '<script>';
    echo 'console.log('. json_encode( $data ) .')';
    echo '</script>';
}
if (!isset($_SESSION)){
    session_start();
}

require_once 'config.php';
require_once 'oAuth.php';

$host = 'api.twitter.com/';

//Trees
// echo '<img 
//     src="trees.jpg" 
//     title="Trees" 
//     alt="Trees" 
//     style="
//         position: absolute;
//         top: 0;
//         left: 25vw;
//         width: 75vw;
//         height: 100vh;
//     "/>';

//Login flow
$isRedirected = false;

//Is logged in
if ( isset( $_SESSION['oauth_secret'] ) && $_SESSION['oauth_secret'] ) { // we have an access token
    echo "Logged in<br><br>";
	$isLoggedIn = true;	
} 
//Is logged in, but coming from Twitter login redirect
// elseif ( isset( $_GET['oauth_verifier'] ) && isset( $_GET['oauth_token'] ) && isset( $_SESSION['oauth_token'] ) && $_GET['oauth_token'] == $_SESSION['oauth_token'] ) { // coming from twitter callback url
elseif ( isset( $_GET['oauth_verifier'] ) && isset( $_GET['oauth_token'] ) && !isset( $_SESSION['oauth_secret'])) { // coming from twitter callback url
    echo "Coming from twitter redirect<br><br>";

    //Updating session variables
    $_SESSION["oauth_verifier"] = explode('oauth_verifier=', $_SERVER['REQUEST_URI'])[1];
    $token = $_SESSION["oauth_token"];
    $verifier = $_SESSION["oauth_verifier"];
    
    //Getting the access token and secret
    //This doesn't require the signed oAuth header, so I didn't bother to make a function for it in oAuth.php
    $options = [
        CURLOPT_URL => "https://api.twitter.com/oauth/access_token?oauth_token=$token&oauth_verifier=$verifier",
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpInfo = curl_getinfo($ch);
    curl_close($ch);

    $info   = explode('&', $response);
    $token  = explode('=', $info[0])[1];
    $secret = explode("=", $info[1])[1];
    $id     = explode("=", $info[2])[1];
    $name   = explode("=", $info[3])[1];

    //More updating session variables
    $_SESSION["oauth_token"]  = $token;
    $_SESSION["oauth_secret"] = $secret;
    $_SESSION["oauth_id"]     = $id;
    $_SESSION["oauth_name"]   = $name;

    
	$isLoggedIn = true;	
    $isRedirected = true;

}
//Is not logged in
else {
    echo "Not logged in<br><br>";
	$isLoggedIn = false;	
}



//Page content


if(!$isLoggedIn) {

    //Unset and reset the oauth token to aviod an authentication error
    unset($_SESSION["oauth_token"]);
    $retval = sendRequest($host, "oauth/request_token");
    $_SESSION["oauth_token"] = $retval["oauth_token"];

    //Login link
    $url = 'https://api.twitter.com/oauth/authorize?oauth_token=' . $retval["oauth_token"];
    ?>
        <a href="<?php echo $url; ?>">Log in With Twitter</a><br><br>
    <?php
}
else {

    //Tweeting text field and button
    ?>
    <form action="index.php" method="post">
        Tweet something: <br><input type="text" name="tweet" style="width: 10vw;"><br>
        <input type="submit" value="Tweet"  style="width: 10vw; height: 30px;";>
    </form>
    <?php 

    //Tweet was posted, process it
    if(isset($_POST["tweet"])) {
        $status = $_POST["tweet"];
        $illegalChar = preg_match('/[æÆøØåÅ]/', $status);
        
        $tweet = [];
        if(strlen($status) < 20) {
            $tweet = [$status, "Tweet er for kort"];
        }
        elseif(strlen($status) > 140) {
            $tweet = [$status, "Tweet er for lang"];
        }
        elseif($illegalChar) {
            $tweet = [$status, "Tweet inneholder ugyldige karakterer"];
        }
        else{
            $response = postTweet($status);
            $json = json_decode($response);

            if(isset($json->text)) {
                $text = $json->text;
                $tweet = [$text, "OK"];
            }
            else {
                echo $json->errors[0]->message;
                $tweet = [$status, $json->errors[0]->message];
            }
        }
    }

    if(!$isRedirected) {
        echo "Recent tweets: <br>";
        if(!isset($_SESSION["arr_head"])) {
            $_SESSION["arr_head"] = -1;
            $_SESSION["tweet_arr"] = [];
        }

        //The idea here is to simulate a circular data structure
        //so that when it reaches the end, it re-begins at the start
        //of the array
        $logging_tweets = 10;
        $head = $_SESSION["arr_head"]+1;
        $head = $head % $logging_tweets;
        $_SESSION["tweet_arr"][$head] = $tweet;


        // // echo var_dump( $_SESSION["tweet_arr"]);
        // //This loop counts backwards from the head to 0, and then from 9 to head + 1
        // for ($x = $head; $x != $head+1%$logging_tweets; $x = ($x > 0 ? $x-1 : $logging_tweets-1)) {
        //     echo "The number is: $x <br>";

        //     if(isset($_SESSION["tweet_arr"][$x])) {
        //         $thisTweet = $_SESSION["tweet_arr"][$x];
        //         echo $thisTweet[0] . "<br>";

        //         if($thisTweet[1] != "OK") {
        //             echo "<span style=\"color:#F00;\">($thisTweet[1])</span><br><br>";
        //         }
        //     }
        // } 
        
        //Successful tweets are black, unsuccessful tweets are red
        //Count from head -> 0
        for ($x = $head; $x >= 0; $x--) {

            if(isset($_SESSION["tweet_arr"][$x])) {
                $thisTweet = $_SESSION["tweet_arr"][$x];

                if($thisTweet[1] == "OK") {
                    echo $thisTweet[0] . "<br><br>";
                }
                else {
                    echo "<span style=\"color:#F00;\">$thisTweet[0]</span><br><span style=\"color:#F00;\">($thisTweet[1])</span><br><br>";
    
                    //Logging tweet and error message, respectively
                    console_log($thisTweet[0]);
                    console_log($thisTweet[1]);
                }
            }
        } 
        //And the other side, from logging_tweets (10) -> head
        for ($x = $logging_tweets-1; $x > $head; $x--) {

            if(isset($_SESSION["tweet_arr"][$x])) {
                $thisTweet = $_SESSION["tweet_arr"][$x];

                if($thisTweet[1] == "OK") {
                    echo $thisTweet[0] . "<br><br>";
                }
                else {
                    echo "<span style=\"color:#F00;\">$thisTweet[0]</span><br><span style=\"color:#F00;\">($thisTweet[1])</span><br><br>";
                }
            }
        } 

        //Update array head
        $_SESSION["arr_head"] = $head;
    }
}
    