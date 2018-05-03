#Bits of Mohawk Search (C)Tsubasa Kato (@stingraze on Twitter) 2018

#DuckDuckGo API
if ($cnt < 20){
	my $datasent3 = $datasent2;	
	Encode::from_to($datasent3,'shift-jis','utf8');

	my $url = "http://api.duckduckgo.com/?q=$datasent3&format=json&pretty=1";
       # Just an example: the URL for the most recent /Fresh Air/ show
	#url .= "

  	my $content = get $url;
  	die "Couldn't get $url" unless defined $content;

	my $results = decode_json($content);

	my @displayName = @{ $results->{RelatedTopics} };

	foreach (@displayName) {

  	my $result =  $_->{Result};
 	Encode::_utf8_off($result);
  	Encode::from_to($result,'utf8','shift-jis');
  	print "<p>&nbsp;$result</p>\n";
	$duckcounter++;
	}
	if ($duckcounter > 0) {	
	print "<p>&nbsp;Powered by DuckDuckGo\n</p>";
	}
}


$SQL_QUERY_CHK_FIRST_NAME=<<__CURSOR_5__;
select firstname from first_names where match (first_names.firstname) against ("$datasent2" IN BOOLEAN MODE);
__CURSOR_5__

$cursor = $dbh->prepare( "$SQL_QUERY_CHK_FIRST_NAME" );
$cursor->execute;  

@rec2 = $cursor->fetchrow_array;

$check_name_first = $rec2[0];

$SQL_QUERY_CHK_NAME=<<__CURSOR_4__;
select lastname from last_names where match (last_names.lastname) against ("$datasent2" IN BOOLEAN MODE);
__CURSOR_4__
$cursor = $dbh->prepare( "$SQL_QUERY_CHK_NAME" );
$cursor->execute;  

@rec3 = $cursor->fetchrow_array;

$check_name_last = $rec3[0];


if ($check_name_first ne $nul && $check_name_last ne $nul) {
	print "<p>Hint: Name detected! Try searching Google+ from the link below. I'm sure you can find the person you're looking for!</p>";
	$searchhere = " <- Click Here to Find more! "
}
print "<p><a href=\"twitter-s.cgi?query=$datasent2\">Search Twitter</a><p>";
print "<p><a href=\"gp.cgi?q=$datasent2\">Search Google+</a>$searchhere<p>";
print "<p><a href=\"thesaurus.cgi?q=$datasent2\">Search Thesaurus</a> (New Function!)<p>";