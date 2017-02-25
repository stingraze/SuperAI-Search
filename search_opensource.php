<?php
    $username = "";
    $password = "";
//Base script from https://owlcation.com/stem/Simple-search-PHP-MySQL
    mysql_connect("localhost", $username, $password) or die("Error connecting to database: ".mysql_error());
    /*
        localhost - it's location of the mysql server, usually localhost
       
         
        if connection fails it will stop loading the page and display an error
    */
     
    mysql_select_db("ows_index") or die(mysql_error());
    /* ows_index is the name of database we've created */
?>
<html>
<head>

<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

<!-- jQuery library -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>

<!-- Latest compiled JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
   
<?php
require_once 'HTTP/Request2.php';

$request = new Http_Request2('https://api.cognitive.microsoft.com/bing/v5.0/search');

$request->setAdapter('curl');

#$request->setAdapter('curl');
$url = $request->getUrl();



$headers = array(
    // Request headers
    'Ocp-Apim-Subscription-Key' => '',
);

$request->setHeader($headers);

$query = $_GET['query']; 



$parameters = array(
    // Request parameters
    'q' => $query,
    'count' => '10',
    'offset' => '0',
    'mkt' => 'en-us',
    'safesearch' => 'Moderate',
);


$url->setQueryVariables($parameters);

$request->setMethod(HTTP_Request2::METHOD_GET);

// Request body
$request->setBody("{body}");

   
       

    // gets value sent over search form

    $min_length = 1;
    // you can set minimum length of the query if you want
     
    if(strlen($query) >= $min_length){ // if query length is more or equal minimum length then
         
        $query = htmlspecialchars($query); 
        // changes characters used in html to their equivalents, for example: < to &gt;
        

        $query = mysql_real_escape_string($query);

        // makes sure nobody uses SQL injection
     
print '<title>SuperAI.online - Search results for: '.$query.'</title>';
?>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta content="text/html; charset=utf-8" http-equiv="content-type">
<link rel="stylesheet" type="text/css" href="./search-style/style2.css"/>
</head>
<body>
           		 <section id="banner2">
			 <br>
             <h1><a href="search.html"><img src="superai-logo.png" alt="SuperAI Search"></a></h1>  
	     <p>The next generation AI Search Engine Powered by Mohawk Search.</p>           
             </section>  
            
     <div class="container">

     <p>
     <form action="search.php" method="get">
	<div class="row">
        <div>
       
            <div id="custom-search-input">
                <div class="input-group col-md-12">
                 
                    <input type="text" class="form-control input-lg" name="query" id="query"/>
                    <span class="input-group-btn">
                        <button class="btn btn-info btn-lg" type="submit">
                            <i class="glyphicon glyphicon-search"></i>
                        </button>
                    </span>
                </div>
            </div>
        </div>
        
	</div>
</form>
</p>
<?php 
	think($query);
        print "You searched for: "." ".$query."<br><br>"; 
        $query = "+".$query;
        $query = str_replace(' ', ' +', $query);

        $raw_results = mysql_query("SELECT title, anchor_text, hostname, page, ((1.3 * (MATCH(title) AGAINST ('$query' IN BOOLEAN MODE))) + (0.6 * (MATCH(anchor_text) AGAINST ('$query' IN BOOLEAN MODE)))) AS relevance FROM pages WHERE (MATCH(title, anchor_text) AGAINST ('$query' IN BOOLEAN MODE) ) ORDER BY relevance DESC") or die(mysql_error());

        $raw_results2 = mysql_query("SELECT title, anchor_text, hostname, page FROM pages
            WHERE title != '' AND match (anchor_text) against ('$query' IN BOOLEAN MODE)  or match (title) against ('$query' WITH QUERY EXPANSION) order by anchor_text <> 'query2', anchor_text LIMIT 150") or die(mysql_error());
        
        if(mysql_num_rows($raw_results) > 0){ // if one or more rows are returned do following
             
            while($results = mysql_fetch_array($raw_results)){
            // $results = mysql_fetch_array($raw_results) puts data from database into array, while it's valid it does the loop
            
                echo '<p><h3><a href="http://'.$results[2].$results[3].'">'.$results[0]."</h3></a>".$results[2].$results[3]."<br><br>".$results[1]."</p>".PHP_EOL;

            }
             
        }
        else{ // if there is no match=ing rows do following
            echo "No results from SuperAI.online with strict matching, expanded results...";


            while($results = mysql_fetch_array($raw_results2)){
            // $results = mysql_fetch_array($raw_results) puts data from database into array, while it's valid it does the loop
            
                echo '<p><h3><a href="http://'.$results[2].$results[3].'">'.$results[0]."</h3></a>".$results[2].$results[3]."<br><br>".$results[1]."</p>".PHP_EOL;
                // posts results gotten from database(title and text) you can also show id ($results['id'])
            }

            echo "<br>Results from Bing<br>";
        try
            {   
    $response = $request->send();
    //echo $response->getBody();
    $result = json_decode($response->getBody(), true);
    print "<p>";
    foreach ($result['webPages']['value'] as $item) {
         echo '<a href="'.$item['url'].'">'.$item['name'].'</a>';
         print "<br>";
         print $item['snippet'];
         print "<br>";
         print $item['displayUrl'];

         print "<br><br>";
         //Make variable $last_title have bing's name
         $last_title = $item['name'].PHP_EOL;
         }
         print "</p>";
    }
        catch (HttpException $ex)
        {
    echo $ex;
        }

        }
         
    }
    else{ // if query length is less than minimum
        echo "Minimum length is ".$min_length;
    }
?>

</p>
<br>
<br>
<iframe src="//rcm-na.amazon-adsystem.com/e/cm?o=1&p=13&l=ez&f=ifr&linkID=f95bacbb71cea7597e498f2cb1d0ab21&t=ppctweakies-20&tracking_id=ppctweakies-20" width="468" height="60" scrolling="no" border="0" marginwidth="0" style="border:none;" frameborder="0"></iframe>
</div> 
<!-- Start of StatCounter Code for Default Guide -->
<script type="text/javascript">
var sc_project=11120522; 
var sc_invisible=1; 
var sc_security="6a588ac2"; 
var sc_https=1; 
var sc_remove_link=1; 
var scJsHost = (("https:" == document.location.protocol) ?
"https://secure." : "http://www.");
document.write("<sc"+"ript type='text/javascript' src='" +
scJsHost+
"statcounter.com/counter/counter.js'></"+"script>");
</script>
<noscript><div class="statcounter"><img class="statcounter"
src="//c.statcounter.com/11120522/0/6a588ac2/1/" alt="web
analytics"></div></noscript>
<!-- End of StatCounter Code for Default Guide -->
</body>
</html>


<?php
//Save to title.txt on server
$file = './title/title.txt';
file_put_contents($file, $last_title, FILE_APPEND | LOCK_EX);

function  think ($query_input)
{
	$tsubasa_age = date("Y") - 1989; 
	$current_month = date("M");	
	if ($current_month < 9){
		$tsubasa_age = $tsubasa_age - 1;
	}

    	if (preg_match("/hello/i", $query_input)){
		echo 'Hi, I\'m glad you decided to talk to me.<br>';

		echo "I was created by Tsubasa Kato, age $tsubasa_age a.k.a stingraze.<br>";
        echo "You can talk to me more below.<br>";
        echo '<iframe width="350" height="430" src="https://console.api.ai/api-client/demo/embedded/c2b9ee77-8217-4d92-8712-b4c21f50261d"></iframe><br>';
	    }
        if ($query_input === 'hi'){
        echo 'Hi, I\'m glad you decided to talk to me.<br>';

        echo "I was created by Tsubasa Kato, age $tsubasa_age a.k.a stingraze.<br>";
        echo "You can talk to me more below.<br>";
        echo '<iframe width="350" height="430" src="https://console.api.ai/api-client/demo/embedded/c2b9ee77-8217-4d92-8712-b4c21f50261d"></iframe><br>';
        }
        if (preg_match("/hi!/i", $query_input)){
        echo 'Hello!<br>';

        echo "I was born in 2016, but my core engine, Mohawk Search was born in 2003. <br>";
        }
        if (preg_match("/bonjour/i", $query_input)){
        echo 'Bonjour!<br>';
        }
        if (preg_match("/Good morning/i", $query_input)){
        echo 'Good morning! How\'s the weather at your place?<br>';
        }
        if (preg_match("/Sing for me/i", $query_input)){
        echo 'I can\'t sing yet, but wait for improvements made in the near future!<br>';
        echo "Check out<a href=\"../../demo/tts.html\"> this demo</a> if you'd like. <br>";
        }
	//Get Weather Function
    //Needs better RegExp
		if (preg_match("/weather for/i", $query_input)){

			if (str_word_count($query_input) >= 3){
					preg_match('/[^ ]*$/', $query_input, $last_word);
					$weather_city = $last_word[0];
					echo "Today's weather for $weather_city<br>";
			        get_weather($weather_city);
	        	}
		}
	
}

//Get Weather Function
function get_weather($weather_input) {
    $url = "http://api.openweathermap.org/data/2.5/weather?q=".$weather_input."&units=metric&APPID=0d8d4c272b4c41de6eee373b7bfe354b";
    $weather = json_decode(file_get_contents($url), true);
    $weather_icon = '<img src="http://openweathermap.org/img/w/%s.png" style="width:200px">';
    echo sprintf($weather_icon, $weather['weather'][0]['icon']);
    echo "<br>";
    
    print($weather['weather'][0]['main']." "."Temperature ".$weather['main']['temp']."°C Humidity: ". $weather['main']['humidity']."% ".$weather['wind']['speed']. " m/s");
    echo "<br>";
    	
	
}   
   

?>

