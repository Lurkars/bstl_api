<?php


echo '<ul>';

$folders = scandir('./init/');

foreach($folders as $path) {
	if ($path != '..' && $path != '.') {
		echo '<li>';
		echo '<a href="?folder='.$path.'">';
		echo $path;
		echo '</a>';
		echo '</li>';
	}
}

echo '<ul>';

if (!isset($_GET['folder'])) {
	exit;
}

if (!preg_match("/\w+/",$_GET['folder']) || !in_array($_GET['folder'], $folders)) {
	echo '<p>Invalid Folder</p>';
	exit;
}

foreach (glob('init/'.$_GET['folder'].'/*.json') as $initFilename) {
    $jsonString = file_get_contents($initFilename);
    echo 'read file: '.$initFilename.'<br />';
    $initDocument = json_decode($jsonString, true);
    if (isset($initDocument)) {
        $jsonPretty = json_encode($initDocument, JSON_PRETTY_PRINT);
        $jsonPretty = preg_replace('#(?<!\\\\)(\\$|\\\\)#', '', $jsonPretty);
        echo'<pre>';
        print_r($jsonPretty);
        echo'</pre><hr/>';
    } else {
		print_r(json_last_error_msg());
        echo 'an error occured <hr/>';
    }
}
