<?php

$base= $_GET['base'];
$name= $_GET['name'];
$org = $_GET['org'];
$url = $_GET['url'];
$desc= $_GET['desc'];
?>
<iframe src="<?php echo $base;?>/res/fake_twitter/fake_twitter.php?base=<?php echo $base; ?>&amp;org=<?php echo $org; ?>&amp;name=<?php echo $name; ?>&amp;url=<?php echo $url; ?>&amp;desc=<?php echo $desc; ?>" frameborder="0" width="100%" height="100%" style="overflow-x:hidden"/>